<?php

declare(strict_types=1);

namespace OCA\NextcloudVideoPlayer\Controller;

use InvalidArgumentException;
use OCA\NextcloudVideoPlayer\Service\FfmpegService;
use OCA\NextcloudVideoPlayer\Service\FolderConfigService;
use OCA\NextcloudVideoPlayer\Service\MediaStreamService;
use OCA\NextcloudVideoPlayer\Service\PlaybackSessionService;
use OCA\NextcloudVideoPlayer\Service\SafeNodeResolver;
use OCA\NextcloudVideoPlayer\Service\VideoCatalogService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\ICallbackResponse;
use OCP\AppFramework\Http\IOutput;
use OCP\AppFramework\Http\Response;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use OCP\IPreview;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUserSession;
use RuntimeException;

class StreamController extends Controller
{
    public const DEFAULT_PLACEHOLDER_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 180"><rect width="320" height="180" fill="#181b20"/><circle cx="160" cy="90" r="34" fill="#0082c9" opacity="0.88"/><polygon points="150,73 180,90 150,107" fill="#ffffff"/></svg>';

    public function __construct(
        string $appName,
        IRequest $request,
        private FolderConfigService $folderConfigService,
        private SafeNodeResolver $safeNodeResolver,
        private VideoCatalogService $catalogService,
        private MediaStreamService $mediaStreamService,
        private FfmpegService $ffmpegService,
        private PlaybackSessionService $sessionService,
        private IUserSession $userSession,
        private ISession $phpSession,
        private IPreview $previewManager,
        private IL10N $l10n
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function stream(string $rootId): Response
    {
        $userId = $this->requireAuthenticatedUserId();
        if ($userId instanceof DataResponse) {
            return $userId;
        }

        $path = (string) $this->request->getParam('path', '');
        $sessionToken = (string) $this->request->getParam('sessionToken', '');
        $transcodeRequested = filter_var($this->request->getParam('transcode', false), FILTER_VALIDATE_BOOLEAN);
        $startSeconds = max(0.0, (float) $this->request->getParam('start', 0));

        try {
            $this->sessionService->validateStreamingSession($userId, $sessionToken, $rootId, $path);
            $effectiveRoots = $this->folderConfigService->getEffectiveRoots($userId);
            $resolved = $this->safeNodeResolver->resolveFileInRoot($userId, $rootId, $path, $effectiveRoots);
            $file = $resolved['file'];

            if (!$this->catalogService->isSupportedVideoFilename($file->getName())) {
                return $this->errorResponse($this->l10n->t('Unsupported video file format'), Http::STATUS_BAD_REQUEST);
            }

            // Release the PHP session lock BEFORE opening/streaming the file so concurrent requests stay responsive
            $this->phpSession->close();

            if ($transcodeRequested && $this->ffmpegService->isTranscodingEnabled()) {
                $stream = $file->fopen('r');
                if (!is_resource($stream)) {
                    return $this->errorResponse($this->l10n->t('Unable to open video file stream'), Http::STATUS_INTERNAL_SERVER_ERROR);
                }
                $ffmpegService = $this->ffmpegService;
                return new class($stream, $startSeconds, $ffmpegService) extends Response implements ICallbackResponse {
                    /**
                     * @param resource $stream
                     */
                    public function __construct(
                        private $stream,
                        private float $startSeconds,
                        private FfmpegService $ffmpegService
                    ) {
                        $this->setStatus(Http::STATUS_OK);
                        $this->addHeader('Content-Type', 'video/mp4');
                        $this->addHeader('Cache-Control', 'no-store');
                        $this->addHeader('X-Content-Type-Options', 'nosniff');
                    }

                    public function callback(IOutput $output): void
                    {
                        $this->ffmpegService->transcodeStreamToCallback(
                            $this->stream,
                            $this->startSeconds,
                            static function (string $chunk) use ($output): void {
                                $output->setOutput($chunk);
                                if (ob_get_level() > 0) {
                                    @ob_flush();
                                }
                                @flush();
                            }
                        );
                    }
                };
            }

            $mimeType = $this->catalogService->getMimeTypeForFilename($file->getName());
            $rangeHeader = $this->request->getHeader('Range');
            $rangeInfo = $this->mediaStreamService->resolveByteRange($file->getSize(), $rangeHeader, $mimeType);

            if ($rangeInfo['status'] === Http::STATUS_REQUEST_RANGE_NOT_SATISFIABLE) {
                $resp = new Response();
                $resp->setStatus(Http::STATUS_REQUEST_RANGE_NOT_SATISFIABLE);
                foreach ($rangeInfo['headers'] as $name => $val) {
                    $resp->addHeader($name, $val);
                }
                return $resp;
            }

            $stream = $file->fopen('r');
            if (!is_resource($stream)) {
                return $this->errorResponse($this->l10n->t('Unable to open video file stream'), Http::STATUS_INTERNAL_SERVER_ERROR);
            }

            $mediaService = $this->mediaStreamService;
            return new class($stream, $rangeInfo, $mediaService) extends Response implements ICallbackResponse {
                /**
                 * @param resource $stream
                 * @param array{status: int, start: int, end: int, length: int, headers: array<string, string>} $rangeInfo
                 */
                public function __construct(
                    private $stream,
                    private array $rangeInfo,
                    private MediaStreamService $mediaStreamService
                ) {
                    $this->setStatus($rangeInfo['status']);
                    foreach ($rangeInfo['headers'] as $header => $value) {
                        $this->addHeader($header, $value);
                    }
                }

                public function callback(IOutput $output): void
                {
                    $this->mediaStreamService->streamRangeSlice(
                        $this->stream,
                        $this->rangeInfo['start'],
                        $this->rangeInfo['length'],
                        static function (string $chunk) use ($output): void {
                            $output->setOutput($chunk);
                            if (ob_get_level() > 0) {
                                @ob_flush();
                            }
                            @flush();
                        }
                    );
                }
            };
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($this->l10n->t($e->getMessage()), Http::STATUS_BAD_REQUEST);
        } catch (NotPermittedException $e) {
            return $this->errorResponse($this->l10n->t($e->getMessage()), Http::STATUS_FORBIDDEN);
        } catch (NotFoundException $e) {
            return $this->errorResponse($this->l10n->t($e->getMessage()), Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function probe(string $rootId): DataResponse
    {
        $userId = $this->requireAuthenticatedUserId();
        if ($userId instanceof DataResponse) {
            return $userId;
        }

        $path = (string) $this->request->getParam('path', '');

        try {
            $effectiveRoots = $this->folderConfigService->getEffectiveRoots($userId);
            $resolved = $this->safeNodeResolver->resolveFileInRoot($userId, $rootId, $path, $effectiveRoots);
            $file = $resolved['file'];

            if (!$this->ffmpegService->isTranscodingEnabled()) {
                return new DataResponse([
                    'duration' => 0.0,
                    'videoCodec' => null,
                    'audioCodec' => null,
                    'canTranscode' => false,
                ]);
            }

            $this->phpSession->close();

            $stream = $file->fopen('r');
            if (is_resource($stream)) {
                try {
                    $info = $this->ffmpegService->probeStreamHeader($stream);
                    if (($info['duration'] ?? 0.0) > 0.0 || $info['videoCodec'] !== null || $info['audioCodec'] !== null) {
                        return new DataResponse($info);
                    }
                } catch (RuntimeException) {
                    // Fall back to probeLocalFile below
                }
            }

            $info = $this->ffmpegService->probeLocalFile($file->getInternalPath());
            return new DataResponse($info);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($this->l10n->t($e->getMessage()), Http::STATUS_BAD_REQUEST);
        } catch (NotPermittedException $e) {
            return $this->errorResponse($this->l10n->t($e->getMessage()), Http::STATUS_FORBIDDEN);
        } catch (NotFoundException $e) {
            return $this->errorResponse($this->l10n->t($e->getMessage()), Http::STATUS_NOT_FOUND);
        } catch (RuntimeException $e) {
            return $this->errorResponse($this->l10n->t($e->getMessage()), Http::STATUS_NOT_IMPLEMENTED);
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function thumbnail(string $rootId): Response
    {
        $userId = $this->requireAuthenticatedUserId();
        if ($userId instanceof DataResponse) {
            return $userId;
        }

        $path = (string) $this->request->getParam('path', '');

        try {
            $effectiveRoots = $this->folderConfigService->getEffectiveRoots($userId);
            $resolved = $this->safeNodeResolver->resolveFileInRoot($userId, $rootId, $path, $effectiveRoots);
            $file = $resolved['file'];

            try {
                $preview = $this->previewManager->getPreview($file, 320, 180, true);
                if ($preview !== null && $preview->valid()) {
                    $imgData = $preview->data();
                    $mime = $preview->mimeType();
                    return $this->buildBinaryResponse($imgData, $mime, 'private, max-age=3600');
                }
            } catch (\Throwable) {
                // Fall back to clean SVG placeholder without writing to media folders
            }

            return $this->buildBinaryResponse(self::DEFAULT_PLACEHOLDER_SVG, 'image/svg+xml', 'private, max-age=300');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($this->l10n->t($e->getMessage()), Http::STATUS_BAD_REQUEST);
        } catch (NotPermittedException $e) {
            return $this->errorResponse($this->l10n->t($e->getMessage()), Http::STATUS_FORBIDDEN);
        } catch (NotFoundException $e) {
            return $this->errorResponse($this->l10n->t($e->getMessage()), Http::STATUS_NOT_FOUND);
        }
    }

    private function buildBinaryResponse(string $body, string $contentType, string $cacheControl): Response
    {
        return new class($body, $contentType, $cacheControl) extends Response implements ICallbackResponse {
            public function __construct(
                private string $body,
                string $contentType,
                string $cacheControl
            ) {
                $this->setStatus(Http::STATUS_OK);
                $this->addHeader('Content-Type', $contentType);
                $this->addHeader('Cache-Control', $cacheControl);
            }

            public function render(): string
            {
                return $this->body;
            }

            public function callback(IOutput $output): void
            {
                $output->setOutput($this->body);
            }
        };
    }

    private function requireAuthenticatedUserId(): string|DataResponse
    {
        if (!$this->userSession->isLoggedIn() || $this->userSession->getUser() === null) {
            return $this->errorResponse($this->l10n->t('Authentication required'), Http::STATUS_UNAUTHORIZED);
        }

        return $this->userSession->getUser()->getUID();
    }

    private function errorResponse(string $message, int $status): DataResponse
    {
        return new DataResponse([
            'error' => [
                'message' => $message,
            ],
        ], $status);
    }
}
