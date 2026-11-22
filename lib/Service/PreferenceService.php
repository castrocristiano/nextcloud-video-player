<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Service;

use OCA\FolderVideoPlayer\Db\UserPreferenceEntity;
use OCA\FolderVideoPlayer\Db\UserPreferenceMapper;

class PreferenceService
{
    private const ALLOWED_RATES = [0.5, 0.75, 1.0, 1.25, 1.5, 2.0];
    private const ALLOWED_SORTS = ['name_asc', 'name_desc', 'date_desc', 'date_asc'];

    public function __construct(
        private UserPreferenceMapper $preferenceMapper
    ) {
    }

    /**
     * @return array{playbackRate: float, autoPlayNext: bool, sortBy: string, volume: float, muted: bool, preferredSubtitle: ?string, updatedAt: int}
     */
    public function getPreferences(string $userId): array
    {
        $entity = $this->preferenceMapper->findByUserId($userId);
        if ($entity === null) {
            return [
                'playbackRate' => 1.0,
                'autoPlayNext' => true,
                'sortBy' => 'name_asc',
                'volume' => 1.0,
                'muted' => false,
                'preferredSubtitle' => null,
                'updatedAt' => 0,
            ];
        }

        return $this->entityToArray($entity);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{playbackRate: float, autoPlayNext: bool, sortBy: string, volume: float, muted: bool, preferredSubtitle: ?string, updatedAt: int}
     */
    public function updatePreferences(string $userId, array $input): array
    {
        $entity = $this->preferenceMapper->findByUserId($userId);
        $isNew = $entity === null;
        if ($entity === null) {
            $entity = new UserPreferenceEntity();
            $entity->setUserId($userId);
            $entity->setPlaybackRate(1.0);
            $entity->setAutoplayNext(true);
            $entity->setSortBy('name_asc');
            $entity->setVolume(1.0);
            $entity->setMuted(false);
            $entity->setPreferredSubtitle(null);
        }

        if (array_key_exists('playbackRate', $input)) {
            $rate = (float) $input['playbackRate'];
            if (in_array($rate, self::ALLOWED_RATES, true)) {
                $entity->setPlaybackRate($rate);
            }
        }

        if (array_key_exists('autoPlayNext', $input)) {
            $entity->setAutoplayNext(filter_var($input['autoPlayNext'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('sortBy', $input)) {
            $sort = (string) $input['sortBy'];
            if (in_array($sort, self::ALLOWED_SORTS, true)) {
                $entity->setSortBy($sort);
            }
        }

        if (array_key_exists('volume', $input)) {
            $vol = min(1.0, max(0.0, (float) $input['volume']));
            $entity->setVolume(round($vol, 2));
        }

        if (array_key_exists('muted', $input)) {
            $entity->setMuted(filter_var($input['muted'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('preferredSubtitle', $input)) {
            $sub = $input['preferredSubtitle'] === null ? null : substr(trim((string) $input['preferredSubtitle']), 0, 32);
            $entity->setPreferredSubtitle($sub === '' ? null : $sub);
        }

        $entity->setUpdatedAt(time());

        if ($isNew) {
            $this->preferenceMapper->insert($entity);
        } else {
            $this->preferenceMapper->update($entity);
        }

        return $this->entityToArray($entity);
    }

    /**
     * @return array{playbackRate: float, autoPlayNext: bool, sortBy: string, volume: float, muted: bool, preferredSubtitle: ?string, updatedAt: int}
     */
    private function entityToArray(UserPreferenceEntity $entity): array
    {
        return [
            'playbackRate' => $entity->getPlaybackRate(),
            'autoPlayNext' => $entity->getAutoplayNext(),
            'sortBy' => $entity->getSortBy(),
            'volume' => $entity->getVolume(),
            'muted' => $entity->getMuted(),
            'preferredSubtitle' => $entity->getPreferredSubtitle(),
            'updatedAt' => $entity->getUpdatedAt(),
        ];
    }
}
