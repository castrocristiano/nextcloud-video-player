<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Db;

use OCA\FolderVideoPlayer\Migration\Version0001Date20261121000000;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<FolderEntity>
 */
class FolderMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, Version0001Date20261121000000::TABLE_FOLDERS, FolderEntity::class);
    }

    /**
     * @return list<FolderEntity>
     */
    public function findGlobalFolders(): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('scope', $qb->createNamedParameter('global')))
            ->orderBy('label', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * @return list<FolderEntity>
     */
    public function findUserFolders(string $userId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('scope', $qb->createNamedParameter('user')))
            ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->orderBy('label', 'ASC');

        return $this->findEntities($qb);
    }

    public function findByRootKey(string $rootKey): ?FolderEntity
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('root_key', $qb->createNamedParameter($rootKey)));

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException) {
            return null;
        }
    }

    public function findByScopeAndPathHash(string $scope, string $userId, string $pathHash): ?FolderEntity
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('scope', $qb->createNamedParameter($scope)))
            ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('path_hash', $qb->createNamedParameter($pathHash)));

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException) {
            return null;
        }
    }

    public function deleteGlobalFolders(): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('scope', $qb->createNamedParameter('global', IQueryBuilder::PARAM_STR)));

        return $qb->executeStatement();
    }
}
