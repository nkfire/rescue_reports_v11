<?php
declare(strict_types=1);
namespace Nkfire\RescueReports\Controller\Backend;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class MigrationController extends ActionController
{
    public function indexAction(): void
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $organisationCount = (int)$connectionPool
            ->getConnectionForTable('tx_rescuereports_domain_model_organisation')
            ->count('uid', 'tx_rescuereports_domain_model_organisation', ['deleted' => 0]);

        $carCount = (int)$connectionPool
            ->getConnectionForTable('tx_rescuereports_domain_model_car')
            ->count('uid', 'tx_rescuereports_domain_model_car', ['deleted' => 0]);

        $this->view->assignMultiple([
            'organisationCount' => $organisationCount,
            'carCount' => $carCount,
            'defaultOrganisations' => "Freiwillige Feuerwehr|FF|🚒\nRettungsdienst|RD|🚑\nPolizei|POL|🚓",
            'defaultCars' => "HLF 20|FF\nTLF 3000|FF\nRTW|RD\nNEF|RD\nStreifenwagen|POL",
        ]);
    }

    public function setupAction(): void
    {
        $pid = max(0, (int)($this->request->hasArgument('pid') ? $this->request->getArgument('pid') : 0));
        $organisationsRaw = (string)($this->request->hasArgument('organisations') ? $this->request->getArgument('organisations') : '');
        $carsRaw = (string)($this->request->hasArgument('cars') ? $this->request->getArgument('cars') : '');

        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $organisationConnection = $connectionPool->getConnectionForTable('tx_rescuereports_domain_model_organisation');
        $carConnection = $connectionPool->getConnectionForTable('tx_rescuereports_domain_model_car');

        $orgMap = $this->loadOrganisationMap($organisationConnection, $pid);
        $createdOrganisations = 0;
        $createdCars = 0;

        foreach ($this->parseOrganisations($organisationsRaw) as $organisation) {
            $lookupName = mb_strtolower($organisation['name']);
            $lookupAbbreviation = mb_strtolower($organisation['abbreviation']);

            if (isset($orgMap[$lookupName]) || ($lookupAbbreviation !== '' && isset($orgMap[$lookupAbbreviation]))) {
                continue;
            }

            $organisationConnection->insert('tx_rescuereports_domain_model_organisation', [
                'pid' => $pid,
                'name' => $organisation['name'],
                'abbreviation' => $organisation['abbreviation'],
                'icon' => $organisation['icon'],
                'tstamp' => $GLOBALS['EXEC_TIME'],
                'crdate' => $GLOBALS['EXEC_TIME'],
            ]);

            $organisationUid = (int)$organisationConnection->lastInsertId('tx_rescuereports_domain_model_organisation');
            $orgMap[$lookupName] = $organisationUid;
            if ($lookupAbbreviation !== '') {
                $orgMap[$lookupAbbreviation] = $organisationUid;
            }
            $createdOrganisations++;
        }

        foreach ($this->parseCars($carsRaw) as $car) {
            $organisationUid = 0;
            if ($car['organisation'] !== '') {
                $organisationUid = (int)($orgMap[mb_strtolower($car['organisation'])] ?? 0);
            }

            if ($this->carExists($carConnection, $pid, $car['name'], $organisationUid)) {
                continue;
            }

            $carConnection->insert('tx_rescuereports_domain_model_car', [
                'pid' => $pid,
                'name' => $car['name'],
                'organization' => $organisationUid,
                'tstamp' => $GLOBALS['EXEC_TIME'],
                'crdate' => $GLOBALS['EXEC_TIME'],
                'sys_language_uid' => 0,
            ]);
            $createdCars++;
        }

        $this->addFlashMessage(
            sprintf('Setup abgeschlossen. Organisationen erstellt: %d, Fahrzeugtypen erstellt: %d.', $createdOrganisations, $createdCars)
        );

        $this->redirect('index');
    }

    public function runAction(): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_rescuereports_einsatz');

        $rows = $connection->select(['uid', 'cars', 'type', 'station'], 'tx_rescuereports_einsatz')->fetchAllAssociative();

        $mmTables = [
            'cars' => 'tx_rescuereports_event_car_mm',
            'type' => 'tx_rescuereports_event_type_mm',
            'station' => 'tx_rescuereports_event_station_mm',
        ];

        $relationCount = ['cars' => 0, 'type' => 0, 'station' => 0];

        foreach ($rows as $row) {
            foreach (['cars', 'type', 'station'] as $field) {
                $values = GeneralUtility::trimExplode(',', (string)$row[$field], true);
                $sorting = 0;
                foreach ($values as $foreignUid) {
                    GeneralUtility::makeInstance(ConnectionPool::class)
                        ->getConnectionForTable($mmTables[$field])
                        ->insert($mmTables[$field], [
                            'uid_local' => (int)$row['uid'],
                            'uid_foreign' => (int)$foreignUid,
                            'tablenames' => '',
                            'sorting' => $sorting++,
                        ]);
                    $relationCount[$field]++;
                }
            }
        }

        $this->addFlashMessage('Migration abgeschlossen:<br>' .
            'Fahrzeuge: ' . $relationCount['cars'] . '<br>' .
            'Typen: ' . $relationCount['type'] . '<br>' .
            'Stationen: ' . $relationCount['station']);

        $this->redirect('index');
    }

    public function resetConfirmAction(): void {}

    public function resetAction(): void
    {
        foreach ([
            'tx_rescuereports_event_car_mm',
            'tx_rescuereports_event_type_mm',
            'tx_rescuereports_event_station_mm',
        ] as $table) {
            GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable($table)
                ->truncate($table);
        }

        $this->addFlashMessage('Alle MM-Einträge wurden zurückgesetzt.');
        $this->redirect('index');
    }

    /**
     * @return array<string,int>
     */
    private function loadOrganisationMap(\TYPO3\CMS\Core\Database\Connection $connection, int $pid): array
    {
        $queryBuilder = $connection->createQueryBuilder();
        $rows = $queryBuilder
            ->select('uid', 'name', 'abbreviation')
            ->from('tx_rescuereports_domain_model_organisation')
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, \PDO::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $map = [];
        foreach ($rows as $row) {
            $uid = (int)$row['uid'];
            $nameKey = mb_strtolower(trim((string)$row['name']));
            if ($nameKey !== '') {
                $map[$nameKey] = $uid;
            }
            $abbrKey = mb_strtolower(trim((string)$row['abbreviation']));
            if ($abbrKey !== '') {
                $map[$abbrKey] = $uid;
            }
        }

        return $map;
    }

    /**
     * @return array<int,array{name:string,abbreviation:string,icon:string}>
     */
    private function parseOrganisations(string $raw): array
    {
        $result = [];
        foreach (preg_split('/\R/u', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            $name = $parts[0] ?? '';
            if ($name === '') {
                continue;
            }

            $result[] = [
                'name' => $name,
                'abbreviation' => $parts[1] ?? '',
                'icon' => $parts[2] ?? '',
            ];
        }

        return $result;
    }

    /**
     * @return array<int,array{name:string,organisation:string}>
     */
    private function parseCars(string $raw): array
    {
        $result = [];
        foreach (preg_split('/\R/u', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            $name = $parts[0] ?? '';
            if ($name === '') {
                continue;
            }

            $result[] = [
                'name' => $name,
                'organisation' => $parts[1] ?? '',
            ];
        }

        return $result;
    }

    private function carExists(\TYPO3\CMS\Core\Database\Connection $connection, int $pid, string $name, int $organisationUid): bool
    {
        $queryBuilder = $connection->createQueryBuilder();
        $count = (int)$queryBuilder
            ->count('uid')
            ->from('tx_rescuereports_domain_model_car')
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('name', $queryBuilder->createNamedParameter($name)),
                $queryBuilder->expr()->eq('organization', $queryBuilder->createNamedParameter($organisationUid, \PDO::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();

        return $count > 0;
    }
}
