<?php

declare(strict_types=1);

namespace OCA\NextcloudVideoPlayer\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getRootKey()
 * @method void setRootKey(string $rootKey)
 * @method string getScope()
 * @method void setScope(string $scope)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getFolderPath()
 * @method void setFolderPath(string $folderPath)
 * @method string getPathHash()
 * @method void setPathHash(string $pathHash)
 * @method string getLabel()
 * @method void setLabel(string $label)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $createdBy)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class FolderEntity extends Entity
{
    protected string $rootKey = '';
    protected string $scope = 'user';
    protected string $userId = '';
    protected string $folderPath = '';
    protected string $pathHash = '';
    protected string $label = '';
    protected string $createdBy = '';
    protected int $createdAt = 0;
    protected int $updatedAt = 0;

    public function __construct()
    {
        $this->addType('rootKey', 'string');
        $this->addType('scope', 'string');
        $this->addType('userId', 'string');
        $this->addType('folderPath', 'string');
        $this->addType('pathHash', 'string');
        $this->addType('label', 'string');
        $this->addType('createdBy', 'string');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
    }
}
