<?php

declare(strict_types=1);

namespace OCA\NextcloudVideoPlayer\BackgroundJob;

use OCA\NextcloudVideoPlayer\Service\PlaybackSessionService;
use OCP\BackgroundJob\TimedJob;

class SessionCleanupJob extends TimedJob
{
    public const INTERVAL_SECONDS = 900; // 15 minutes

    public function __construct(
        private PlaybackSessionService $sessionService
    ) {
        parent::__construct();
        $this->setInterval(self::INTERVAL_SECONDS);
    }

    public function run(mixed $argument): void
    {
        $this->sessionService->cleanupExpiredSessions();
    }
}
