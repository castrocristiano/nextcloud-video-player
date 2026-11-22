<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method float getPlaybackRate()
 * @method void setPlaybackRate(float $playbackRate)
 * @method bool getAutoplayNext()
 * @method void setAutoplayNext(bool $autoplayNext)
 * @method string getSortBy()
 * @method void setSortBy(string $sortBy)
 * @method float getVolume()
 * @method void setVolume(float $volume)
 * @method bool getMuted()
 * @method void setMuted(bool $muted)
 * @method ?string getPreferredSubtitle()
 * @method void setPreferredSubtitle(?string $preferredSubtitle)
 * @method ?string getLastRootId()
 * @method void setLastRootId(?string $lastRootId)
 * @method ?string getLastFolderPath()
 * @method void setLastFolderPath(?string $lastFolderPath)
 * @method float getSubtitleScale()
 * @method void setSubtitleScale(float $subtitleScale)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class UserPreferenceEntity extends Entity
{
    protected string $userId = '';
    protected float $playbackRate = 1.0;
    protected bool $autoplayNext = true;
    protected string $sortBy = 'name_asc';
    protected float $volume = 1.0;
    protected bool $muted = false;
    protected ?string $preferredSubtitle = null;
    protected ?string $lastRootId = null;
    protected ?string $lastFolderPath = null;
    protected float $subtitleScale = 1.0;
    protected int $updatedAt = 0;

    public function __construct()
    {
        $this->addType('userId', 'string');
        $this->addType('playbackRate', 'float');
        $this->addType('autoplayNext', 'boolean');
        $this->addType('sortBy', 'string');
        $this->addType('volume', 'float');
        $this->addType('muted', 'boolean');
        $this->addType('preferredSubtitle', 'string');
        $this->addType('lastRootId', 'string');
        $this->addType('lastFolderPath', 'string');
        $this->addType('subtitleScale', 'float');
        $this->addType('updatedAt', 'integer');
    }
}
