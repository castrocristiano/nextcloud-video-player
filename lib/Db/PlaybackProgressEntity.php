<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method ?int getFileId()
 * @method void setFileId(?int $fileId)
 * @method string getRootKey()
 * @method void setRootKey(string $rootKey)
 * @method string getRelativePath()
 * @method void setRelativePath(string $relativePath)
 * @method string getPathHash()
 * @method void setPathHash(string $pathHash)
 * @method float getPositionSeconds()
 * @method void setPositionSeconds(float $positionSeconds)
 * @method float getDurationSeconds()
 * @method void setDurationSeconds(float $durationSeconds)
 * @method float getProgressRatio()
 * @method void setProgressRatio(float $progressRatio)
 * @method bool getCompleted()
 * @method void setCompleted(bool $completed)
 * @method ?string getLastSessionToken()
 * @method void setLastSessionToken(?string $lastSessionToken)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class PlaybackProgressEntity extends Entity
{
    protected string $userId = '';
    protected ?int $fileId = null;
    protected string $rootKey = '';
    protected string $relativePath = '';
    protected string $pathHash = '';
    protected float $positionSeconds = 0.0;
    protected float $durationSeconds = 0.0;
    protected float $progressRatio = 0.0;
    protected bool $completed = false;
    protected ?string $lastSessionToken = null;
    protected int $updatedAt = 0;

    public function __construct()
    {
        $this->addType('userId', 'string');
        $this->addType('fileId', 'integer');
        $this->addType('rootKey', 'string');
        $this->addType('relativePath', 'string');
        $this->addType('pathHash', 'string');
        $this->addType('positionSeconds', 'float');
        $this->addType('durationSeconds', 'float');
        $this->addType('progressRatio', 'float');
        $this->addType('completed', 'boolean');
        $this->addType('lastSessionToken', 'string');
        $this->addType('updatedAt', 'integer');
    }
}
