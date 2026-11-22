<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Service;

use OCP\AppFramework\Http;

class MediaStreamService
{
    public const CHUNK_SIZE = 65536; // 64 KiB

    /**
     * Parses an HTTP Range header against a known file size.
     *
     * @return array{status: int, start: int, end: int, length: int, headers: array<string, string>}
     */
    public function resolveByteRange(int $fileSize, string $rangeHeader, string $mimeType): array
    {
        $safeSize = max(0, $fileSize);
        $baseHeaders = [
            'Content-Type' => $mimeType,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, no-transform',
            'X-Content-Type-Options' => 'nosniff',
        ];

        $trimmed = trim($rangeHeader);
        if ($trimmed === '' || $safeSize === 0) {
            $baseHeaders['Content-Length'] = (string) $safeSize;
            return [
                'status' => Http::STATUS_OK,
                'start' => 0,
                'end' => max(0, $safeSize - 1),
                'length' => $safeSize,
                'headers' => $baseHeaders,
            ];
        }

        if (!str_starts_with($trimmed, 'bytes=')) {
            return $this->unsatisfiableRange($safeSize, $baseHeaders);
        }

        $rangeSpec = trim(substr($trimmed, 6));
        // If multi-range is requested, take only or reject malformed
        if (str_contains($rangeSpec, ',')) {
            $rangeSpec = trim(explode(',', $rangeSpec)[0]);
        }

        if (!preg_match('/^(\d*)-(\d*)$/', $rangeSpec, $matches)) {
            return $this->unsatisfiableRange($safeSize, $baseHeaders);
        }

        $startStr = $matches[1];
        $endStr = $matches[2];

        if ($startStr === '' && $endStr === '') {
            return $this->unsatisfiableRange($safeSize, $baseHeaders);
        }

        if ($startStr === '') {
            // Suffix range: bytes=-500
            $suffixLength = (int) $endStr;
            if ($suffixLength <= 0) {
                return $this->unsatisfiableRange($safeSize, $baseHeaders);
            }
            $start = max(0, $safeSize - $suffixLength);
            $end = $safeSize - 1;
        } else {
            $start = (int) $startStr;
            $end = $endStr === '' ? ($safeSize - 1) : min($safeSize - 1, (int) $endStr);
        }

        if ($start < 0 || $start >= $safeSize || $end < $start) {
            return $this->unsatisfiableRange($safeSize, $baseHeaders);
        }

        $length = ($end - $start) + 1;
        $baseHeaders['Content-Range'] = "bytes {$start}-{$end}/{$safeSize}";
        $baseHeaders['Content-Length'] = (string) $length;

        return [
            'status' => Http::STATUS_PARTIAL_CONTENT,
            'start' => $start,
            'end' => $end,
            'length' => $length,
            'headers' => $baseHeaders,
        ];
    }

    /**
     * Streams the requested byte slice from a readable stream resource to a writer callable.
     *
     * @param resource $stream
     * @param callable(string): void $writer
     */
    public function streamRangeSlice($stream, int $start, int $length, callable $writer): void
    {
        try {
            if ($length <= 0) {
                return;
            }

            if ($start > 0) {
                @fseek($stream, $start);
            }

            $remaining = $length;
            while ($remaining > 0 && !feof($stream)) {
                $readSize = min(self::CHUNK_SIZE, $remaining);
                $chunk = fread($stream, $readSize);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $writer($chunk);
                $remaining -= strlen($chunk);
            }
        } finally {
            if (is_resource($stream)) {
                @fclose($stream);
            }
        }
    }

    /**
     * @param array<string, string> $baseHeaders
     * @return array{status: int, start: int, end: int, length: int, headers: array<string, string>}
     */
    private function unsatisfiableRange(int $fileSize, array $baseHeaders): array
    {
        $baseHeaders['Content-Range'] = "bytes */{$fileSize}";
        $baseHeaders['Content-Length'] = '0';

        return [
            'status' => Http::STATUS_REQUEST_RANGE_NOT_SATISFIABLE,
            'start' => 0,
            'end' => 0,
            'length' => 0,
            'headers' => $baseHeaders,
        ];
    }
}
