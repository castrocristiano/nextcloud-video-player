<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Service;

use RuntimeException;

class ConcurrentSessionLimitException extends RuntimeException
{
    /**
     * @param list<array<string, mixed>> $activeSessions
     */
    public function __construct(
        string $message,
        private int $maxConcurrentSessions,
        private array $activeSessions = []
    ) {
        parent::__construct($message);
    }

    public function getMaxConcurrentSessions(): int
    {
        return $this->maxConcurrentSessions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getActiveSessions(): array
    {
        return $this->activeSessions;
    }
}
