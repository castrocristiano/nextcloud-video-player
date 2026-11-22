<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Controller;

use InvalidArgumentException;
use OCA\FolderVideoPlayer\Service\FfmpegService;
use OCA\FolderVideoPlayer\Service\FolderConfigService;
use OCA\FolderVideoPlayer\Service\MediaStreamService;
use OCA\FolderVideoPlayer\Service\PlaybackSessionService;
use OCA\FolderVideoPlayer\Service\SafeNodeResolver;
use OCA\FolderVideoPlayer\Service\VideoCatalogService;
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
use OCP\ICacheFactory;
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
        private IL10N $l10n,
        private ?ICacheFactory $cacheFactory = null
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
                $fileSize = (int) $file->getSize();
                $mtime = (int) $file->getMTime();
                $cachePrefix = 'blk_' . hash('sha256', $rootId . '|' . $resolved['relativePath'] . '|' . $fileSize . '|' . $mtime);
                $cacheDir = rtrim(sys_get_temp_dir(), '/') . '/ncvp_transcode_cache';
                $transcodeKey = hash('sha256', $cachePrefix . '|' . (int) round($startSeconds));
                $cachedMp4 = $cacheDir . '/' . $transcodeKey . '.mp4';

                if (is_file($cachedMp4)) {
                    $cachedSize = @filesize($cachedMp4);
                    if (is_int($cachedSize) && $cachedSize > 0) {
                        @touch($cachedMp4);
                        $rangeInfo = $this->mediaStreamService->resolveByteRange(
                            $cachedSize,
                            $this->request->getHeader('Range'),
                            'video/mp4'
                        );
                        if ($rangeInfo['status'] === Http::STATUS_REQUEST_RANGE_NOT_SATISFIABLE) {
                            return $this->buildRangeUnsatisfiableResponse($rangeInfo);
                        }
                        $cachedStream = @fopen($cachedMp4, 'rb');
                        if (is_resource($cachedStream)) {
                            return $this->buildRangeStreamResponse($cachedStream, $rangeInfo);
                        }
                    }
                }

                $stream = $file->fopen('r');
                if (!is_resource($stream)) {
                    return $this->errorResponse($this->l10n->t('Unable to open video file stream'), Http::STATUS_INTERNAL_SERVER_ERROR);
                }
                $ffmpegService = $this->ffmpegService;
                return new class($stream, $startSeconds, $ffmpegService, $fileSize, $cachePrefix, $cacheDir, $cachedMp4) extends Response implements ICallbackResponse {
                    /**
                     * @param resource $stream
                     */
                    public function __construct(
                        private $stream,
                        private float $startSeconds,
                        private FfmpegService $ffmpegService,
                        private int $fileSize,
                        private string $cachePrefix,
                        private string $cacheDir,
                        private string $cachedMp4
                    ) {
                        $this->setStatus(Http::STATUS_OK);
                        $this->addHeader('Content-Type', 'video/mp4');
                        $this->addHeader('Cache-Control', 'no-store');
                        $this->addHeader('X-Content-Type-Options', 'nosniff');
                    }

                    public function callback(IOutput $output): void
                    {
                        if (!is_dir($this->cacheDir)) {
                            @mkdir($this->cacheDir, 0770, true);
                        }
                        $partMp4 = $this->cachedMp4 . '.' . bin2hex(random_bytes(6)) . '.part';
                        $partHandle = @fopen($partMp4, 'wb');
                        $writeFailed = false;

                        $this->ffmpegService->transcodeStreamToCallback(
                            $this->stream,
                            $this->startSeconds,
                            static function (string $chunk) use ($output, $partHandle, &$writeFailed): void {
                                if (is_resource($partHandle) && !$writeFailed) {
                                    if (@fwrite($partHandle, $chunk) === false) {
                                        $writeFailed = true;
                                    }
                                }
                                $output->setOutput($chunk);
                                if (ob_get_level() > 0) {
                                    @ob_flush();
                                }
                                @flush();
                            },
                            $this->fileSize,
                            $this->cachePrefix
                        );

                        if (is_resource($partHandle)) {
                            @fclose($partHandle);
                        }
                        if (
                            !$writeFailed
                            && connection_aborted() === 0
                            && is_file($partMp4)
                            && ((int) @filesize($partMp4)) > 0
                        ) {
                            @rename($partMp4, $this->cachedMp4);
                            StreamController::pruneTranscodeCache($this->cacheDir);
                        } elseif (is_file($partMp4)) {
                            @unlink($partMp4);
                        }
                    }
                };
            }

            $mimeType = $this->catalogService->getMimeTypeForFilename($file->getName());
            $rangeHeader = $this->request->getHeader('Range');
            $rangeInfo = $this->mediaStreamService->resolveByteRange($file->getSize(), $rangeHeader, $mimeType);

            if ($rangeInfo['status'] === Http::STATUS_REQUEST_RANGE_NOT_SATISFIABLE) {
                return $this->buildRangeUnsatisfiableResponse($rangeInfo);
            }

            $stream = $file->fopen('r');
            if (!is_resource($stream)) {
                return $this->errorResponse($this->l10n->t('Unable to open video file stream'), Http::STATUS_INTERNAL_SERVER_ERROR);
            }

            return $this->buildRangeStreamResponse($stream, $rangeInfo);
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

            $cache = $this->cacheFactory?->createDistributed('folder_video_player_probe');
            $cacheKey = sprintf('probe_%s_%s_%d_%d', $rootId, hash('sha256', $resolved['relativePath']), $file->getSize(), $file->getMTime());
            if ($cache !== null) {
                $cached = $cache->get($cacheKey);
                if (is_array($cached) && isset($cached['duration'])) {
                    return new DataResponse($cached);
                }
            }

            $stream = $file->fopen('r');
            if (is_resource($stream)) {
                try {
                    $info = $this->ffmpegService->probeStreamHeader($stream);
                    if (($info['duration'] ?? 0.0) > 0.0 || $info['videoCodec'] !== null || $info['audioCodec'] !== null) {
                        $cache?->set($cacheKey, $info, 86400);
                        return new DataResponse($info);
                    }
                } catch (RuntimeException) {
                    // Fall back to probeLocalFile below
                }
            }

            $info = $this->ffmpegService->probeLocalFile($file->getInternalPath());
            $cache?->set($cacheKey, $info, 86400);
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

    /**
     * @param array{status: int, start: int, end: int, length: int, headers: array<string, string>} $rangeInfo
     */
    private function buildRangeUnsatisfiableResponse(array $rangeInfo): Response
    {
        $resp = new Response();
        $resp->setStatus(Http::STATUS_REQUEST_RANGE_NOT_SATISFIABLE);
        foreach ($rangeInfo['headers'] as $name => $val) {
            $resp->addHeader($name, $val);
        }
        return $resp;
    }

    /**
     * @param resource $stream
     * @param array{status: int, start: int, end: int, length: int, headers: array<string, string>} $rangeInfo
     */
    private function buildRangeStreamResponse($stream, array $rangeInfo): Response
    {
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
    }

    public static function pruneTranscodeCache(string $cacheDir, int $maxBytes = 2147483648): void
    {
        $files = @glob($cacheDir . '/*');
        if (!is_array($files)) {
            return;
        }

        $now = time();
        /** @var list<array{path: string, size: int, mtime: int}> $entries */
        $entries = [];
        $totalSize = 0;

        foreach ($files as $path) {
            if (!is_file($path)) {
                continue;
            }
            $mtime = (int) (@filemtime($path) ?: 0);
            if (str_ends_with($path, '.part')) {
                if (($now - $mtime) > 3600) {
                    @unlink($path);
                }
                continue;
            }
            $size = (int) (@filesize($path) ?: 0);
            $entries[] = ['path' => $path, 'size' => $size, 'mtime' => $mtime];
            $totalSize += $size;
        }

        if ($totalSize <= $maxBytes) {
            return;
        }

        usort($entries, static fn (array $a, array $b): int => $a['mtime'] <=> $b['mtime']);
        foreach ($entries as $entry) {
            if ($totalSize <= $maxBytes) {
                break;
            }
            if (@unlink($entry['path'])) {
                $totalSize -= $entry['size'];
            }
        }
    }
}
