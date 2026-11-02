<?php
declare(strict_types=1);

namespace Nkfire\RescueReports\Hooks;

use PDO;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DataHandlerHook
{
    public function processDatamap_preProcessFieldArray(array &$incomingFieldArray, $table, $id, $parentObject): void
    {
        if ($table !== 'tx_rescuereports_domain_model_event') {
            return;
        }

        if ($this->isKeywordEscalationDisabled($incomingFieldArray)) {
            $incomingFieldArray['keyword_escalation'] = 0;
        }

        $title = trim((string)($incomingFieldArray['title'] ?? ''));
        if ($title === '') {
            $title = 'einsatz';
        }

        $start = (string)($incomingFieldArray['start'] ?? '');
        $typeTitle = $this->resolveTypeTitle($incomingFieldArray, $id);

        $titleSlug = $this->slugify($title);
        $typeSlug = $this->slugify($typeTitle);

        $slugParts = [];

        if ($start !== '') {
            $timestamp = strtotime($start);
            if ($timestamp !== false) {
                $slugParts[] = date('m', $timestamp);
                $slugParts[] = date('d', $timestamp);
            }
        }

        $lastPart = '';
        if ($typeSlug !== '') {
            $lastPart .= $typeSlug;
        }
        if ($titleSlug !== '') {
            $lastPart .= ($lastPart !== '' ? '/' : '') . $titleSlug;
        }

        if ($lastPart === '') {
            $lastPart = 'einsatz';
        }

        $slugParts[] = $lastPart;

        $incomingFieldArray['slug_source'] = implode('/', $slugParts);

        // nur wenn du automatische Neugenerierung willst
        $incomingFieldArray['slug'] = '';
    }

    public function processDatamap_afterDatabaseOperations(
        $status,
        $table,
        $id,
        $fieldArray,
        $parentObject
    ): void {
        if ($table !== 'tx_rescuereports_domain_model_event') {
            return;
        }

        if (!$this->isKeywordEscalationDisabled($fieldArray)) {
            return;
        }

        $eventUid = $status === 'new'
            ? (int)($parentObject->substNEWwithIDs[$id] ?? 0)
            : (int)$id;

        if ($eventUid <= 0) {
            return;
        }

        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $connectionPool
            ->getConnectionForTable('tx_rescuereports_event_keyword_escalation_mm')
            ->delete(
                'tx_rescuereports_event_keyword_escalation_mm',
                ['uid_local' => $eventUid]
            );

        $connectionPool
            ->getConnectionForTable('tx_rescuereports_domain_model_event')
            ->update(
                'tx_rescuereports_domain_model_event',
                ['keyword_escalation' => 0],
                ['uid' => $eventUid]
            );
    }

    protected function isKeywordEscalationDisabled(array $incomingFieldArray): bool
    {
        if (!array_key_exists('enable_keyword_escalation', $incomingFieldArray)) {
            return false;
        }

        return (int)$incomingFieldArray['enable_keyword_escalation'] === 0;
    }

    protected function resolveTypeTitle(array $incomingFieldArray, $id): string
    {
        $typeUid = 0;
        $useEscalation = (int)($incomingFieldArray['enable_keyword_escalation'] ?? 0) === 1;

        // Bei aktivierter Stichworterhöhung: escalation verwenden statt types
        if ($useEscalation && !empty($incomingFieldArray['keyword_escalation'])) {
            $typeUid = (int)$incomingFieldArray['keyword_escalation'];
        } elseif ($useEscalation && (int)$id > 0) {
            $typeUid = $this->getKeywordEscalationUidFromMm((int)$id);
        }

        // Fallback auf types, wenn keine Escalation vorhanden
        if ($typeUid <= 0) {
            if (!empty($incomingFieldArray['types'])) {
                $typeUid = (int)$incomingFieldArray['types'];
            }

            if ($typeUid <= 0 && (int)$id > 0) {
                $typeUid = $this->getTypeUidFromMm((int)$id);
            }
        }

        if ($typeUid <= 0) {
            return '';
        }

        return $this->getTypeTitleByUid($typeUid);
    }

    protected function getTypeUidFromMm(int $eventUid): int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_rescuereports_event_type_mm');

        $row = $queryBuilder
            ->select('uid_foreign')
            ->from('tx_rescuereports_event_type_mm')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid_local',
                    $queryBuilder->createNamedParameter($eventUid, PDO::PARAM_INT)
                )
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return (int)($row['uid_foreign'] ?? 0);
    }

    protected function getKeywordEscalationUidFromMm(int $eventUid): int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_rescuereports_event_keyword_escalation_mm');

        $row = $queryBuilder
            ->select('uid_foreign')
            ->from('tx_rescuereports_event_keyword_escalation_mm')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid_local',
                    $queryBuilder->createNamedParameter($eventUid, PDO::PARAM_INT)
                )
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return (int)($row['uid_foreign'] ?? 0);
    }

    protected function getTypeTitleByUid(int $typeUid): string
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_rescuereports_domain_model_type');

        $queryBuilder->getRestrictions()->removeAll();

        $row = $queryBuilder
            ->select('title')
            ->from('tx_rescuereports_domain_model_type')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($typeUid, PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'deleted',
                    $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)
                )
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return trim((string)($row['title'] ?? ''));
    }

    protected function slugify(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^[:alnum:]]+/u', '-', $text);
        $text = trim((string)$text, '-');

        return $text !== '' ? $text : '';
    }
}