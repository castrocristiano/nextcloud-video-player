<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Tests\Unit\Service;

use OCA\FolderVideoPlayer\Service\SubtitleService;
use OCP\Files\File;
use PHPUnit\Framework\TestCase;

class SubtitleServiceTest extends TestCase
{
    public function testDiscoversMatchingVttAndSrtAndConvertsSrtToWebVttInMemory(): void
    {
        $videoFile = $this->createMock(File::class);
        $videoFile->method('isReadable')->willReturn(true);
        $videoFile->method('getName')->willReturn('Episode01.mp4');

        $srtPt = $this->createMock(File::class);
        $srtPt->method('isReadable')->willReturn(true);
        $srtPt->method('getName')->willReturn('Episode01.pt-BR.srt');
        $srtPt->method('getContent')->willReturn("1\r\n00:00:01,250 --> 00:00:04,500\r\nOlá mundo!\r\n");

        $vttEn = $this->createMock(File::class);
        $vttEn->method('isReadable')->willReturn(true);
        $vttEn->method('getName')->willReturn('Episode01.en.vtt');
        $vttEn->method('getContent')->willReturn("WEBVTT\n\n00:00:01.000 --> 00:00:03.000\nHello world!");

        $otherSub = $this->createMock(File::class);
        $otherSub->method('isReadable')->willReturn(true);
        $otherSub->method('getName')->willReturn('Episode02.pt-BR.srt');

        $service = new SubtitleService();
        $found = $service->discoverSubtitlesForVideo('Episode01.mp4', 'Season 1', [
            $videoFile,
            $srtPt,
            $vttEn,
            $otherSub,
        ]);

        $this->assertCount(2, $found);
        $this->assertSame('en', $found[0]['lang']);
        $this->assertSame('/Season 1/Episode01.en.vtt', $found[0]['relativePath']);
        $this->assertSame('pt-BR', $found[1]['lang']);
        $this->assertSame('/Season 1/Episode01.pt-BR.srt', $found[1]['relativePath']);

        $converted = $service->readAsWebVtt($srtPt);
        $this->assertStringStartsWith("WEBVTT\n\n", $converted);
        $this->assertStringContainsString('00:00:01.250 --> 00:00:04.500', $converted);
        $this->assertStringContainsString('Olá mundo!', $converted);

        $vttOut = $service->readAsWebVtt($vttEn);
        $this->assertStringStartsWith("WEBVTT\n\n", $vttOut);
    }
}
