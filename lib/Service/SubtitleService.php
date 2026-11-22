<?php

declare(strict_types=1);

namespace OCA\NextcloudVideoPlayer\Service;

use InvalidArgumentException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;

class SubtitleService
{
    private const SUPPORTED_EXTENSIONS = ['vtt', 'srt'];

    /**
     * Discovers sibling subtitle files (.vtt and .srt) in the same folder that match the video's base name.
     *
     * @param list<\OCP\Files\Node> $folderNodes
     * @return list<array{filename: string, relativePath: string, format: string, lang: string, label: string}>
     */
    public function discoverSubtitlesForVideo(
        string $videoFilename,
        string $folderRelativePath,
        array $folderNodes
    ): array {
        $videoBase = $this->stripExtension($videoFilename);
        if ($videoBase === '') {
            return [];
        }

        $videoBaseLower = mb_strtolower($videoBase, 'UTF-8');
        $cleanDir = trim($folderRelativePath, '/');
        $subtitles = [];

        foreach ($folderNodes as $node) {
            if (!$node instanceof File || !$node->isReadable()) {
                continue;
            }

            $name = $node->getName();
            if ($name === '' || str_starts_with($name, '.')) {
                continue;
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, self::SUPPORTED_EXTENSIONS, true)) {
                continue;
            }

            $subBase = $this->stripExtension($name);
            $subBaseLower = mb_strtolower($subBase, 'UTF-8');

            $suffix = '';
            if ($subBaseLower === $videoBaseLower) {
                $suffix = '';
            } elseif (str_starts_with($subBaseLower, $videoBaseLower . '.')) {
                $suffix = substr($subBase, strlen($videoBase) + 1);
            } elseif (str_starts_with($subBaseLower, $videoBaseLower . '_')) {
                $suffix = substr($subBase, strlen($videoBase) + 1);
            } else {
                continue;
            }

            $lang = $suffix !== '' ? $suffix : 'default';
            $label = $suffix !== '' ? $suffix . ' (.' . $ext . ')' : 'Default (.' . $ext . ')';
            $relPath = $cleanDir === '' ? '/' . $name : '/' . $cleanDir . '/' . $name;

            $subtitles[] = [
                'filename' => $name,
                'relativePath' => $relPath,
                'format' => $ext,
                'lang' => $lang,
                'label' => $label,
            ];
        }

        usort(
            $subtitles,
            static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label'])
        );

        return $subtitles;
    }

    /**
     * Reads a .vtt or .srt file node and returns WebVTT content in memory without modifying storage.
     */
    public function readAsWebVtt(File $subtitleFile): string
    {
        $ext = strtolower(pathinfo($subtitleFile->getName(), PATHINFO_EXTENSION));
        if (!in_array($ext, self::SUPPORTED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('Unsupported subtitle file extension');
        }

        $raw = $subtitleFile->getContent();
        if ($ext === 'vtt') {
            return $this->normalizeVttContent($raw);
        }

        return $this->convertSrtToWebVtt($raw);
    }

    public function convertSrtToWebVtt(string $srtContent): string
    {
        $clean = preg_replace('/^\xEF\xBB\xBF/', '', $srtContent) ?? $srtContent;
        $clean = str_replace(["\r\n", "\r"], "\n", $clean);

        // Replace SRT comma milliseconds (00:01:23,456 --> 00:01:25,789) with WebVTT dots
        $converted = preg_replace(
            '/(\d{2}:\d{2}:\d{2}),(\d{3})\s*-->\s*(\d{2}:\d{2}:\d{2}),(\d{3})/',
            '$1.$2 --> $3.$4',
            $clean
        ) ?? $clean;

        return "WEBVTT\n\n" . trim($converted) . "\n";
    }

    /**
     * Resolves and reads a subtitle file inside a configured root folder.
     *
     * @param list<array{id: string, path: string, label: string, scope: string}> $effectiveRoots
     */
    public function getWebVttForPath(
        string $userId,
        string $rootKey,
        string $relativePath,
        array $effectiveRoots,
        SafeNodeResolver $resolver
    ): string {
        $resolved = $resolver->resolveFileInRoot($userId, $rootKey, $relativePath, $effectiveRoots);
        $file = $resolved['file'];
        $ext = strtolower(pathinfo($file->getName(), PATHINFO_EXTENSION));
        if (!in_array($ext, self::SUPPORTED_EXTENSIONS, true)) {
            throw new NotFoundException('Requested file is not a supported subtitle');
        }

        return $this->readAsWebVtt($file);
    }

    private function normalizeVttContent(string $vttContent): string
    {
        $clean = preg_replace('/^\xEF\xBB\xBF/', '', $vttContent) ?? $vttContent;
        $clean = str_replace(["\r\n", "\r"], "\n", trim($clean));
        if (!str_starts_with($clean, 'WEBVTT')) {
            return "WEBVTT\n\n" . $clean . "\n";
        }
        return $clean . "\n";
    }

    private function stripExtension(string $filename): string
    {
        $pos = strrpos($filename, '.');
        if ($pos === false || $pos === 0) {
            return '';
        }
        return substr($filename, 0, $pos);
    }
}
