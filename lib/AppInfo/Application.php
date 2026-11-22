<?php

declare(strict_types=1);

namespace OCA\NextcloudVideoPlayer\AppInfo;

use OCA\NextcloudVideoPlayer\Listener\UserLoggedOutListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IContainer;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\User\Events\UserLoggedOutEvent;

class Application extends App implements IBootstrap
{
    public const APP_ID = 'nextcloud_video_player';
    public const APP_VERSION = '0.1.4';

    public function __construct(array $urlParams = [])
    {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void
    {
        $context->registerService(IL10N::class, static function (IContainer $c): IL10N {
            return $c->get(IFactory::class)->get(self::APP_ID);
        });

        $context->registerEventListener(
            UserLoggedOutEvent::class,
            UserLoggedOutListener::class
        );
    }

    public function boot(IBootContext $context): void
    {
        $container = $context->getServerContainer();

        /** @var INavigationManager $navigationManager */
        $navigationManager = $container->get(INavigationManager::class);
        $navigationManager->add(static function () use ($container): array {
            /** @var IURLGenerator $urlGenerator */
            $urlGenerator = $container->get(IURLGenerator::class);
            /** @var IFactory $l10nFactory */
            $l10nFactory = $container->get(IFactory::class);
            $l10n = $l10nFactory->get(self::APP_ID);

            return [
                'id' => self::APP_ID,
                'order' => 15,
                'href' => $urlGenerator->linkToRoute(self::APP_ID . '.page.index'),
                'icon' => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),
                'name' => $l10n->t('Video Player'),
            ];
        });
    }
}
