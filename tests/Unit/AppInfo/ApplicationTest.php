<?php

declare(strict_types=1);

namespace OCA\NextcloudVideoPlayer\Tests\Unit\AppInfo;

use OCA\NextcloudVideoPlayer\AppInfo\Application;
use OCA\NextcloudVideoPlayer\Listener\UserLoggedOutListener;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IServerContainer;
use OCP\IURLGenerator;
use OCP\User\Events\UserLoggedOutEvent;
use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase
{
    public function testRegisterBindsLogoutListener(): void
    {
        $app = new Application();
        $registrationContext = $this->createMock(IRegistrationContext::class);
        $registrationContext->expects($this->once())
            ->method('registerEventListener')
            ->with(UserLoggedOutEvent::class, UserLoggedOutListener::class);

        $app->register($registrationContext);
        $this->assertSame('nextcloud_video_player', $app->getAppName());
    }

    public function testBootRegistersLocalizedNavigationEntry(): void
    {
        $app = new Application();

        $capturedClosure = null;
        $navManager = $this->createMock(INavigationManager::class);
        $navManager->expects($this->once())
            ->method('add')
            ->willReturnCallback(static function (callable $closure) use (&$capturedClosure): void {
                $capturedClosure = $closure;
            });

        $urlGen = $this->createMock(IURLGenerator::class);
        $urlGen->method('linkToRoute')
            ->with('nextcloud_video_player.page.index')
            ->willReturn('/apps/nextcloud_video_player/');
        $urlGen->method('imagePath')
            ->with('nextcloud_video_player', 'app.svg')
            ->willReturn('/apps/nextcloud_video_player/img/app.svg');

        $l10n = $this->createMock(IL10N::class);
        $l10n->method('t')
            ->with('Video Player')
            ->willReturn('Player de Vídeo');

        $container = $this->createMock(IServerContainer::class);
        $container->method('get')
            ->willReturnCallback(static fn (string $id): object => match ($id) {
                INavigationManager::class => $navManager,
                IURLGenerator::class => $urlGen,
                IL10N::class => $l10n,
                default => throw new \RuntimeException("Unexpected service {$id}"),
            });

        $bootContext = $this->createMock(IBootContext::class);
        $bootContext->method('getServerContainer')->willReturn($container);

        $app->boot($bootContext);
        $this->assertIsCallable($capturedClosure);

        $entry = $capturedClosure();
        $this->assertSame('nextcloud_video_player', $entry['id']);
        $this->assertSame('/apps/nextcloud_video_player/', $entry['href']);
        $this->assertSame('/apps/nextcloud_video_player/img/app.svg', $entry['icon']);
        $this->assertSame('Player de Vídeo', $entry['name']);
    }
}
