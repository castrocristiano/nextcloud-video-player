<?php

declare(strict_types=1);

namespace OCA\NextcloudVideoPlayer\Service;

use OCA\NextcloudVideoPlayer\AppInfo\Application;
use OCP\IConfig;
use RuntimeException;

class FfmpegService
{
    /** @var null|callable(list<string>): array{exitCode: int, stdout: string, stderr: string} */
    private $commandRunner;

    /**
     * @param null|callable(list<string>): array{exitCode: int, stdout: string, stderr: string} $commandRunner
     */
    public function __construct(
        private IConfig $config,
        ?callable $commandRunner = null
    ) {
        $this->commandRunner = $commandRunner;
    }

    public function isTranscodingEnabled(): bool
    {
        return $this->config->getAppValue(Application::APP_ID, 'ffmpeg_enabled', '0') === '1';
    }

    public function getFfmpegBinary(): string
    {
        $bin = trim($this->config->getAppValue(Application::APP_ID, 'ffmpeg_path', 'ffmpeg'));
        return $bin !== '' ? $bin : 'ffmpeg';
    }

    public function getFfprobeBinary(): string
    {
        $bin = trim($this->config->getAppValue(Application::APP_ID, 'ffprobe_path', 'ffprobe'));
        return $bin !== '' ? $bin : 'ffprobe';
    }

    /**
     * Probes video duration and stream codecs via ffprobe when enabled.
     *
     * @return array{duration: float, videoCodec: ?string, audioCodec: ?string, canTranscode: bool}
     */
    public function probeLocalFile(string $localFilePath): array
    {
        if (!$this->isTranscodingEnabled()) {
            throw new RuntimeException('FFmpeg transcoding is disabled by administrator');
        }

        $cmd = [
            $this->getFfprobeBinary(),
            '-v',
            'quiet',
            '-print_format',
            'json',
            '-show_format',
            '-show_streams',
            $localFilePath,
        ];

        $res = $this->runCommand($cmd);
        if ($res['exitCode'] !== 0 || trim($res['stdout']) === '') {
            throw new RuntimeException('Failed to probe media file with ffprobe');
        }

        $decoded = json_decode($res['stdout'], true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid ffprobe JSON output');
        }

        $duration = isset($decoded['format']['duration']) ? max(0.0, (float) $decoded['format']['duration']) : 0.0;
        $videoCodec = null;
        $audioCodec = null;

        if (isset($decoded['streams']) && is_array($decoded['streams'])) {
            foreach ($decoded['streams'] as $stream) {
                if (!is_array($stream)) {
                    continue;
                }
                $type = (string) ($stream['codec_type'] ?? '');
                $codec = isset($stream['codec_name']) ? (string) $stream['codec_name'] : null;
                if ($type === 'video' && $videoCodec === null) {
                    $videoCodec = $codec;
                } elseif ($type === 'audio' && $audioCodec === null) {
                    $audioCodec = $codec;
                }
            }
        }

        return [
            'duration' => round($duration, 2),
            'videoCodec' => $videoCodec,
            'audioCodec' => $audioCodec,
            'canTranscode' => true,
        ];
    }

    /**
     * Builds the safe argument array for real-time fragmented MP4 (H.264/AAC) transcoding to stdout.
     *
     * @return list<string>
     */
    public function buildTranscodeCommand(string $inputPath, float $startSeconds = 0.0): array
    {
        $safeStart = max(0.0, round($startSeconds, 2));
        $cmd = [
            $this->getFfmpegBinary(),
            '-nostdin',
            '-hide_banner',
            '-loglevel',
            'error',
        ];

        if ($safeStart > 0.0) {
            $cmd[] = '-ss';
            $cmd[] = (string) $safeStart;
        }

        array_push(
            $cmd,
            '-i',
            $inputPath,
            '-c:v',
            'libx264',
            '-preset',
            'ultrafast',
            '-pix_fmt',
            'yuv420p',
            '-c:a',
            'aac',
            '-b:a',
            '128k',
            '-ac',
            '2',
            '-movflags',
            'frag_keyframe+empty_moov+default_base_moof',
            '-f',
            'mp4',
            'pipe:1'
        );

        return $cmd;
    }

    /**
     * @param list<string> $cmd
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function runCommand(array $cmd): array
    {
        if ($this->commandRunner !== null) {
            return ($this->commandRunner)($cmd);
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return ['exitCode' => 1, 'stdout' => '', 'stderr' => 'Unable to start process'];
        }

        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'exitCode' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }
}
