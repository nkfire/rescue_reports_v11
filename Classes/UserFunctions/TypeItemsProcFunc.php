<?php
declare(strict_types=1);

namespace Nkfire\RescueReports\UserFunctions;

use PDO;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TypeItemsProcFunc
{
    public function filterDeprecatedTypes(array &$params): void
    {
        $currentTypeUids = $this->getCurrentlySelectedTypeUids($params);
        $filteredItems = [];

        foreach ($params['items'] as $item) {
            $typeUid = (int)($item[1] ?? 0);

            // Leerer Eintrag bleibt erhalten
            if ($typeUid === 0) {
                $filteredItems[] = $item;
                continue;
            }

            $typeRecord = $this->getTypeRecord($typeUid);

            // Falls Datensatz nicht gelesen werden kann: lieber drinlassen
            if (!$typeRecord) {
                $filteredItems[] = $item;
                continue;
            }

            $isDeprecated = (int)($typeRecord['deprecated'] ?? 0) === 1;

            // Bereits beim Datensatz ausgewählte Einsatzarten immer anzeigen
            if (in_array($typeUid, $currentTypeUids, true)) {
                if ($isDeprecated) {
                    $item[0] .= ' [veraltet]';
                }
                $filteredItems[] = $item;
                continue;
            }

            // Für neue Auswahl nur nicht-veraltete Typen anzeigen
            if (!$isDeprecated) {
                $filteredItems[] = $item;
            }
        }

        $params['items'] = $filteredItems;
    }

    protected function getCurrentlySelectedTypeUids(array $params): array
    {
        $eventUid = (int)($params['row']['uid'] ?? 0);

        // Neuer Datensatz ohne UID
        if ($eventUid <= 0) {
            return [];
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_rescuereports_event_type_mm');

        $rows = $queryBuilder
            ->select('uid_foreign')
            ->from('tx_rescuereports_event_type_mm')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid_local',
                    $queryBuilder->createNamedParameter($eventUid, PDO::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchFirstColumn();

        return array_map('intval', $rows ?: []);
    }

    protected function getTypeRecord(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_rescuereports_domain_model_type');

        // Standard-Restrictions entfernen, damit auch versteckte /
        // zeitgesteuerte Einträge gefunden werden können, wenn sie
        // bereits an alten Einsätzen hängen
        $queryBuilder->getRestrictions()->removeAll();

        $record = $queryBuilder
            ->select('uid', 'title', 'deprecated', 'hidden', 'starttime', 'endtime', 'deleted')
            ->from('tx_rescuereports_domain_model_type')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'deleted',
                    $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAssociative();

        return $record ?: null;
    }
}