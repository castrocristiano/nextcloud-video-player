<?php

declare(strict_types=1);

namespace OCA\NextcloudVideoPlayer\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getSessionToken()
 * @method void setSessionToken(string $sessionToken)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getRootKey()
 * @method void setRootKey(string $rootKey)
 * @method string getRelativePath()
 * @method void setRelativePath(string $relativePath)
 * @method string getStreamMode()
 * @method void setStreamMode(string $streamMode)
 * @method ?string getClientAgent()
 * @method void setClientAgent(?string $clientAgent)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method int getStartedAt()
 * @method void setStartedAt(int $startedAt)
 * @method int getLastHeartbeatAt()
 * @method void setLastHeartbeatAt(int $lastHeartbeatAt)
 * @method int getExpiresAt()
 * @method void setExpiresAt(int $expiresAt)
 * @method ?int getEndedAt()
 * @method void setEndedAt(?int $endedAt)
 */
class PlaybackSessionEntity extends Entity
{
    protected string $sessionToken = '';
    protected string $userId = '';
    protected string $rootKey = '';
    protected string $relativePath = '';
    protected string $streamMode = 'direct';
    protected ?string $clientAgent = null;
    protected string $status = 'active';
    protected int $startedAt = 0;
    protected int $lastHeartbeatAt = 0;
    protected int $expiresAt = 0;
    protected ?int $endedAt = null;

    public function __construct()
    {
        $this->addType('sessionToken', 'string');
        $this->addType('userId', 'string');
        $this->addType('rootKey', 'string');
        $this->addType('relativePath', 'string');
        $this->addType('streamMode', 'string');
        $this->addType('clientAgent', 'string');
        $this->addType('status', 'string');
        $this->addType('startedAt', 'integer');
        $this->addType('lastHeartbeatAt', 'integer');
        $this->addType('expiresAt', 'integer');
        $this->addType('endedAt', 'integer');
    }
}
