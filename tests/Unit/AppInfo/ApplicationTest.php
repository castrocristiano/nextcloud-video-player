<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Tests\Unit\AppInfo;

use OCA\FolderVideoPlayer\AppInfo\Application;
use OCA\FolderVideoPlayer\Listener\UserLoggedOutListener;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IContainer;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IServerContainer;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\User\Events\UserLoggedOutEvent;
use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase
{
    public function testRegisterBindsL10nServiceAndLogoutListener(): void
    {
        $app = new Application();
        $capturedServiceFactory = null;

        $registrationContext = $this->createMock(IRegistrationContext::class);
        $registrationContext->expects($this->once())
            ->method('registerService')
            ->with(IL10N::class, $this->isType('callable'))
            ->willReturnCallback(static function (string $name, \Closure $closure) use (&$capturedServiceFactory): void {
                $capturedServiceFactory = $closure;
            });
        $registrationContext->expects($this->once())
            ->method('registerEventListener')
            ->with(UserLoggedOutEvent::class, UserLoggedOutListener::class);

        $app->register($registrationContext);
        $this->assertSame('folder_video_player', $app->getAppName());
        $this->assertIsCallable($capturedServiceFactory);

        $l10n = $this->createMock(IL10N::class);
        $factory = $this->createMock(IFactory::class);
        $factory->method('get')->with('folder_video_player')->willReturn($l10n);
        $container = $this->createMock(IContainer::class);
        $container->method('get')->with(IFactory::class)->willReturn($factory);

        $this->assertSame($l10n, $capturedServiceFactory($container));
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
            ->with('folder_video_player.page.index')
            ->willReturn('/apps/folder_video_player/');
        $urlGen->method('imagePath')
            ->with('folder_video_player', 'app.svg')
            ->willReturn('/apps/folder_video_player/img/app.svg');

        $l10n = $this->createMock(IL10N::class);
        $l10n->method('t')
            ->with('Video Player')
            ->willReturn('Player de Vídeo');

        $l10nFactory = $this->createMock(IFactory::class);
        $l10nFactory->method('get')
            ->with('folder_video_player')
            ->willReturn($l10n);

        $container = $this->createMock(IServerContainer::class);
        $container->method('get')
            ->willReturnCallback(static fn (string $id): object => match ($id) {
                INavigationManager::class => $navManager,
                IURLGenerator::class => $urlGen,
                IFactory::class => $l10nFactory,
                default => throw new \RuntimeException("Unexpected service {$id}"),
            });

        $bootContext = $this->createMock(IBootContext::class);
        $bootContext->method('getServerContainer')->willReturn($container);

        $app->boot($bootContext);
        $this->assertIsCallable($capturedClosure);

        $entry = $capturedClosure();
        $this->assertSame('folder_video_player', $entry['id']);
        $this->assertSame('/apps/folder_video_player/', $entry['href']);
        $this->assertSame('/apps/folder_video_player/img/app.svg', $entry['icon']);
        $this->assertSame('Player de Vídeo', $entry['name']);
    }
}
