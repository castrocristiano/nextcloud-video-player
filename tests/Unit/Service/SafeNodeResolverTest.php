<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\FolderVideoPlayer\Service\SafeNodeResolver;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use PHPUnit\Framework\TestCase;

class SafeNodeResolverTest extends TestCase
{
    public function testNormalizeFolderPathAndTraversalProtection(): void
    {
        $rootFolder = $this->createMock(IRootFolder::class);
        $resolver = new SafeNodeResolver($rootFolder);

        $this->assertSame('/Movies/SciFi', $resolver->normalizeFolderPath(' /Movies//SciFi/ '));
        $this->assertSame('Season 1/Ep01.mp4', $resolver->normalizeRelativePath('/Season 1//Ep01.mp4'));
        $this->assertSame('', $resolver->normalizeRelativePath('/'));

        $this->expectException(InvalidArgumentException::class);
        $resolver->normalizeFolderPath('/Movies/../../etc/passwd');
    }

    public function testRejectsNullByteAndBackslashAndHiddenRelativePaths(): void
    {
        $rootFolder = $this->createMock(IRootFolder::class);
        $resolver = new SafeNodeResolver($rootFolder);

        $nullByteRejected = false;
        try {
            $resolver->normalizeFolderPath("/Movies\0/bad");
        } catch (InvalidArgumentException) {
            $nullByteRejected = true;
        }
        $this->assertSame(true, $nullByteRejected);

        $backslashRejected = false;
        try {
            $resolver->normalizeRelativePath('..\\secret');
        } catch (InvalidArgumentException) {
            $backslashRejected = true;
        }
        $this->assertSame(true, $backslashRejected);

        $this->expectException(NotPermittedException::class);
        $resolver->normalizeRelativePath('.hidden/video.mp4');
    }

    public function testResolveFileInConfiguredRootEnforcesBoundaries(): void
    {
        $fileNode = $this->createMock(File::class);
        $fileNode->method('isReadable')->willReturn(true);
        $fileNode->method('getPath')->willReturn('/alice/files/Movies/Action/film.mp4');

        $moviesFolder = $this->createMock(Folder::class);
        $moviesFolder->method('isReadable')->willReturn(true);
        $moviesFolder->method('getPath')->willReturn('/alice/files/Movies');
        $moviesFolder->method('nodeExists')->with('Action/film.mp4')->willReturn(true);
        $moviesFolder->method('get')->with('Action/film.mp4')->willReturn($fileNode);

        $userFolder = $this->createMock(Folder::class);
        $userFolder->method('nodeExists')->with('Movies')->willReturn(true);
        $userFolder->method('get')->with('Movies')->willReturn($moviesFolder);

        $rootFolder = $this->createMock(IRootFolder::class);
        $rootFolder->method('getUserFolder')->with('alice')->willReturn($userFolder);

        $resolver = new SafeNodeResolver($rootFolder);
        $roots = [
            ['id' => 'rk1', 'path' => '/Movies', 'label' => 'Movies', 'scope' => 'global'],
        ];

        $resolved = $resolver->resolveFileInRoot('alice', 'rk1', 'Action/film.mp4', $roots);
        $this->assertSame($fileNode, $resolved['file']);
        $this->assertSame('/Action/film.mp4', $resolved['relativePath']);

        $this->expectException(NotPermittedException::class);
        $resolver->resolveFileInRoot('alice', 'unknown_root', 'Action/film.mp4', $roots);
    }

    public function testRejectsNodeEscapingRootPrefix(): void
    {
        $escapedFile = $this->createMock(File::class);
        $escapedFile->method('isReadable')->willReturn(true);
        $escapedFile->method('getPath')->willReturn('/alice/files/Private/secret.mp4');

        $moviesFolder = $this->createMock(Folder::class);
        $moviesFolder->method('isReadable')->willReturn(true);
        $moviesFolder->method('getPath')->willReturn('/alice/files/Movies');
        $moviesFolder->method('nodeExists')->with('symlink.mp4')->willReturn(true);
        $moviesFolder->method('get')->with('symlink.mp4')->willReturn($escapedFile);

        $userFolder = $this->createMock(Folder::class);
        $userFolder->method('nodeExists')->with('Movies')->willReturn(true);
        $userFolder->method('get')->with('Movies')->willReturn($moviesFolder);

        $rootFolder = $this->createMock(IRootFolder::class);
        $rootFolder->method('getUserFolder')->with('alice')->willReturn($userFolder);

        $resolver = new SafeNodeResolver($rootFolder);
        $roots = [
            ['id' => 'rk1', 'path' => '/Movies', 'label' => 'Movies', 'scope' => 'global'],
        ];

        $this->expectException(NotPermittedException::class);
        $resolver->resolveFileInRoot('alice', 'rk1', 'symlink.mp4', $roots);
    }

    public function testThrowsNotFoundWhenFileOrFolderMissing(): void
    {
        $moviesFolder = $this->createMock(Folder::class);
        $moviesFolder->method('isReadable')->willReturn(true);
        $moviesFolder->method('getPath')->willReturn('/alice/files/Movies');
        $moviesFolder->method('nodeExists')->with('missing.mp4')->willReturn(false);

        $userFolder = $this->createMock(Folder::class);
        $userFolder->method('nodeExists')->with('Movies')->willReturn(true);
        $userFolder->method('get')->with('Movies')->willReturn($moviesFolder);

        $rootFolder = $this->createMock(IRootFolder::class);
        $rootFolder->method('getUserFolder')->with('alice')->willReturn($userFolder);

        $resolver = new SafeNodeResolver($rootFolder);
        $roots = [
            ['id' => 'rk1', 'path' => '/Movies', 'label' => 'Movies', 'scope' => 'global'],
        ];

        $this->expectException(NotFoundException::class);
        $resolver->resolveFileInRoot('alice', 'rk1', 'missing.mp4', $roots);
    }
}
