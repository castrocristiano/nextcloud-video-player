<?php

declare(strict_types=1);

namespace OCA\NextcloudVideoPlayer\Tests\Unit\Service;

use OCA\NextcloudVideoPlayer\Service\FfmpegService;
use OCA\NextcloudVideoPlayer\Service\MediaStreamService;
use OCP\AppFramework\Http;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class MediaStreamAndFfmpegServiceTest extends TestCase
{
    public function testResolveByteRangeAndStreamRangeSlice(): void
    {
        $service = new MediaStreamService();

        // Full 200 OK when Range header omitted
        $full = $service->resolveByteRange(1000, '', 'video/mp4');
        $this->assertSame(Http::STATUS_OK, $full['status']);
        $this->assertSame(0, $full['start']);
        $this->assertSame(999, $full['end']);
        $this->assertSame(1000, $full['length']);

        // Partial 206 for explicit range
        $partial = $service->resolveByteRange(1000, 'bytes=100-299', 'video/mp4');
        $this->assertSame(Http::STATUS_PARTIAL_CONTENT, $partial['status']);
        $this->assertSame(100, $partial['start']);
        $this->assertSame(299, $partial['end']);
        $this->assertSame(200, $partial['length']);
        $this->assertSame('bytes 100-299/1000', $partial['headers']['Content-Range']);

        // Suffix range
        $suffix = $service->resolveByteRange(1000, 'bytes=-250', 'video/mp4');
        $this->assertSame(Http::STATUS_PARTIAL_CONTENT, $suffix['status']);
        $this->assertSame(750, $suffix['start']);
        $this->assertSame(999, $suffix['end']);
        $this->assertSame(250, $suffix['length']);

        // Invalid range -> 416
        $invalid = $service->resolveByteRange(1000, 'bytes=2000-2500', 'video/mp4');
        $this->assertSame(Http::STATUS_REQUEST_RANGE_NOT_SATISFIABLE, $invalid['status']);
        $this->assertSame('bytes */1000', $invalid['headers']['Content-Range']);

        // Stream slice reading
        $mem = fopen('php://memory', 'r+');
        $this->assertIsResource($mem);
        fwrite($mem, '0123456789ABCDEFGHIJ');
        rewind($mem);

        $collected = '';
        $service->streamRangeSlice($mem, 10, 5, static function (string $chunk) use (&$collected): void {
            $collected .= $chunk;
        });
        $this->assertSame('ABCDE', $collected);
    }

    public function testFfmpegProbeAndTranscodeCommandBuilder(): void
    {
        $config = $this->createMock(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(static fn (string $app, string $key, string $def = ''): string => match ($key) {
            'ffmpeg_enabled' => '1',
            'ffmpeg_path' => '/usr/bin/ffmpeg',
            'ffprobe_path' => '/usr/bin/ffprobe',
            default => $def,
        });

        $runner = static function (array $cmd): array {
            return [
                'exitCode' => 0,
                'stdout' => (string) json_encode([
                    'format' => ['duration' => '142.75'],
                    'streams' => [
                        ['codec_type' => 'video', 'codec_name' => 'mpeg4'],
                        ['codec_type' => 'audio', 'codec_name' => 'mp3'],
                    ],
                ]),
                'stderr' => '',
            ];
        };

        $ffmpeg = new FfmpegService($config, $runner);
        $this->assertTrue($ffmpeg->isTranscodingEnabled());

        $probed = $ffmpeg->probeLocalFile('/tmp/video.avi');
        $this->assertSame(142.75, $probed['duration']);
        $this->assertSame('mpeg4', $probed['videoCodec']);
        $this->assertSame('mp3', $probed['audioCodec']);
        $this->assertTrue($probed['canTranscode']);

        $cmd = $ffmpeg->buildTranscodeCommand('/tmp/video.avi', 35.5);
        $this->assertContains('-ss', $cmd);
        $this->assertContains('35.5', $cmd);
        $this->assertContains('frag_keyframe+empty_moov+default_base_moof', $cmd);
    }
}
