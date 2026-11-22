<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Db;

use OCA\FolderVideoPlayer\Migration\Version0001Date20261121000000;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<PlaybackSessionEntity>
 */
class PlaybackSessionMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, Version0001Date20261121000000::TABLE_SESSIONS, PlaybackSessionEntity::class);
    }

    public function findByToken(string $sessionToken): ?PlaybackSessionEntity
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('session_token', $qb->createNamedParameter($sessionToken)));

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException) {
            return null;
        }
    }

    /**
     * @return list<PlaybackSessionEntity>
     */
    public function findActiveForUser(string $userId, int $nowTimestamp): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->in('status', $qb->createNamedParameter(['active', 'paused'], IQueryBuilder::PARAM_STR_ARRAY)))
            ->andWhere($qb->expr()->gte('expires_at', $qb->createNamedParameter($nowTimestamp, IQueryBuilder::PARAM_INT)))
            ->orderBy('started_at', 'ASC');

        return $this->findEntities($qb);
    }

    public function expireStaleSessions(int $nowTimestamp, ?string $userId = null): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->update($this->getTableName())
            ->set('status', $qb->createNamedParameter('expired'))
            ->set('ended_at', $qb->createNamedParameter($nowTimestamp, IQueryBuilder::PARAM_INT))
            ->where($qb->expr()->in('status', $qb->createNamedParameter(['active', 'paused'], IQueryBuilder::PARAM_STR_ARRAY)))
            ->andWhere($qb->expr()->lt('expires_at', $qb->createNamedParameter($nowTimestamp, IQueryBuilder::PARAM_INT)));

        if ($userId !== null && $userId !== '') {
            $qb->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
        }

        return $qb->executeStatement();
    }

    public function revokeAllForUser(string $userId, int $nowTimestamp): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->update($this->getTableName())
            ->set('status', $qb->createNamedParameter('revoked'))
            ->set('ended_at', $qb->createNamedParameter($nowTimestamp, IQueryBuilder::PARAM_INT))
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->in('status', $qb->createNamedParameter(['active', 'paused'], IQueryBuilder::PARAM_STR_ARRAY)));

        return $qb->executeStatement();
    }

    public function purgeEndedBefore(int $cutoffTimestamp): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->in('status', $qb->createNamedParameter(['ended', 'expired', 'revoked'], IQueryBuilder::PARAM_STR_ARRAY)))
            ->andWhere($qb->expr()->lt('last_heartbeat_at', $qb->createNamedParameter($cutoffTimestamp, IQueryBuilder::PARAM_INT)));

        return $qb->executeStatement();
    }
}
