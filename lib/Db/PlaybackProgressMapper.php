<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Db;

use OCA\FolderVideoPlayer\Migration\Version0001Date20261121000000;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<PlaybackProgressEntity>
 */
class PlaybackProgressMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, Version0001Date20261121000000::TABLE_PROGRESS, PlaybackProgressEntity::class);
    }

    public function findByUserAndVideo(string $userId, string $rootKey, string $pathHash): ?PlaybackProgressEntity
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('root_key', $qb->createNamedParameter($rootKey)))
            ->andWhere($qb->expr()->eq('path_hash', $qb->createNamedParameter($pathHash)));

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException) {
            return null;
        }
    }

    /**
     * @return list<PlaybackProgressEntity>
     */
    public function findAllForUserAndRoot(string $userId, string $rootKey): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('root_key', $qb->createNamedParameter($rootKey)));

        return $this->findEntities($qb);
    }

    /**
     * @return list<PlaybackProgressEntity>
     */
    public function findAllForUser(string $userId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->orderBy('updated_at', 'DESC');

        return $this->findEntities($qb);
    }

    /**
     * @return list<PlaybackProgressEntity>
     */
    public function findRecentUnfinishedForUser(string $userId, int $limit = 10): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('completed', $qb->createNamedParameter(false, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL)))
            ->andWhere($qb->expr()->gt('position_seconds', $qb->createNamedParameter(3.0)))
            ->orderBy('updated_at', 'DESC')
            ->setMaxResults(max(1, $limit));

        return $this->findEntities($qb);
    }
}
