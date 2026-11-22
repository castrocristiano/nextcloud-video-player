<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Service;

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
        $utf8 = $this->ensureUtf8($raw);
        if ($ext === 'vtt') {
            return $this->normalizeVttContent($utf8);
        }

        return $this->convertSrtToWebVtt($utf8);
    }

    public function convertSrtToWebVtt(string $srtContent): string
    {
        $utf8 = $this->ensureUtf8($srtContent);
        $clean = preg_replace('/^\xEF\xBB\xBF/', '', $utf8) ?? $utf8;
        $clean = str_replace(["\r\n", "\r"], "\n", $clean);

        // Replace SRT comma or dot milliseconds (00:01:23,456 --> 00:01:25,789 or 01:23,456) with WebVTT dots
        $converted = preg_replace(
            '/(\d{1,2}:\d{2}(?::\d{2})?),(\d{3})\s*-->\s*(\d{1,2}:\d{2}(?::\d{2})?),(\d{3})/',
            '$1.$2 --> $3.$4',
            $clean
        ) ?? $clean;

        return "WEBVTT\n\n" . trim($converted) . "\n";
    }

    private function ensureUtf8(string $content): string
    {
        if (mb_check_encoding($content, 'UTF-8')) {
            return $content;
        }

        $converted = @mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        if (is_string($converted) && $converted !== '') {
            return $converted;
        }

        return mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
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

    /**
     * Shifts all WebVTT cue timestamps by $shiftSeconds.
     * Positive $shiftSeconds delays subtitles; negative $shiftSeconds advances subtitles (e.g. when seeking a transcoded video).
     */
    public function shiftWebVttTimestamps(string $vttContent, float $shiftSeconds): string
    {
        if (abs($shiftSeconds) < 0.001) {
            return $vttContent;
        }

        return (string) preg_replace_callback(
            '/(\d{2}:\d{2}:\d{2}\.\d{3})\s*-->\s*(\d{2}:\d{2}:\d{2}\.\d{3})/',
            function (array $m) use ($shiftSeconds): string {
                $rawStart = $this->parseVttTime($m[1]) + $shiftSeconds;
                $rawEnd = $this->parseVttTime($m[2]) + $shiftSeconds;
                $start = max(0.0, $rawStart);
                $end = max($start + 0.1, $rawEnd);
                return $this->formatVttTime($start) . ' --> ' . $this->formatVttTime($end);
            },
            $vttContent
        );
    }

    private function parseVttTime(string $vttTime): float
    {
        [$hms, $ms] = array_pad(explode('.', $vttTime, 2), 2, '0');
        $parts = array_map('intval', explode(':', $hms));
        $hours = $parts[0] ?? 0;
        $minutes = $parts[1] ?? 0;
        $seconds = $parts[2] ?? 0;
        return ($hours * 3600) + ($minutes * 60) + $seconds + (((int) $ms) / 1000.0);
    }

    private function formatVttTime(float $totalSeconds): string
    {
        $clamped = max(0.0, $totalSeconds);
        $whole = (int) floor($clamped);
        $ms = (int) round(($clamped - $whole) * 1000);
        if ($ms >= 1000) {
            $whole += 1;
            $ms -= 1000;
        }
        $hours = intdiv($whole, 3600);
        $minutes = intdiv($whole % 3600, 60);
        $seconds = $whole % 60;
        return sprintf('%02d:%02d:%02d.%03d', $hours, $minutes, $seconds, $ms);
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
