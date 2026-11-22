<?php

declare(strict_types=1);

namespace OCA\NextcloudVideoPlayer\Db;

use OCA\NextcloudVideoPlayer\Migration\Version0001Date20261121000000;
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
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

        return $this->findEntities($qb);
    }
}
