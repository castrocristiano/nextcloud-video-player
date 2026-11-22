<?php

declare(strict_types=1);

namespace OCA\NextcloudVideoPlayer\Db;

use OCA\NextcloudVideoPlayer\Migration\Version0001Date20261121000000;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<UserPreferenceEntity>
 */
class UserPreferenceMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, Version0001Date20261121000000::TABLE_PREFERENCES, UserPreferenceEntity::class);
    }

    public function findByUserId(string $userId): ?UserPreferenceEntity
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException) {
            return null;
        }
    }
}
