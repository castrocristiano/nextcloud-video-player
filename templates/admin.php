<?php

declare(strict_types=1);

/** @var \OCP\IL10N $l */
/** @var array<string, mixed> $_ */
$globalFolders = isset($_['globalFolders']) && is_array($_['globalFolders']) ? $_['globalFolders'] : [];
$ffmpegEnabled = !empty($_['ffmpegEnabled']);
$ffmpegPath = isset($_['ffmpegPath']) ? (string) $_['ffmpegPath'] : 'ffmpeg';
$ffprobePath = isset($_['ffprobePath']) ? (string) $_['ffprobePath'] : 'ffprobe';
$sessionTtl = isset($_['sessionTtlSeconds']) ? (int) $_['sessionTtlSeconds'] : 180;
$maxConcurrent = isset($_['maxConcurrentSessions']) ? (int) $_['maxConcurrentSessions'] : 0;
?>
<div id="ncvp-admin-settings" class="section ncvp-admin-section">
	<h2><?php p($l->t('Nextcloud Video Player')); ?></h2>
	<p class="settings-hint">
		<?php p($l->t('Configure pre-defined global video folders and playback session policies. Users will only see global folders for which they have native Nextcloud read permissions.')); ?>
	</p>

	<form id="ncvpAdminForm" class="ncvp-admin-form">
		<div class="ncvp-admin-field">
			<label for="ncvpGlobalFoldersInput">
				<strong><?php p($l->t('Pre-configured Global Folders (one path per line, optional label after "|")')); ?></strong>
			</label>
			<p class="ncvp-admin-help">
				<?php p($l->t('Example: /Movies | Movies Library')); ?>
			</p>
			<textarea id="ncvpGlobalFoldersInput" rows="5" class="ncvp-admin-textarea"><?php
                $lines = [];
                foreach ($globalFolders as $folder) {
                    if (is_array($folder) && isset($folder['path'])) {
                        $path = (string) $folder['path'];
                        $label = isset($folder['label']) ? (string) $folder['label'] : basename($path);
                        $lines[] = $path . ' | ' . $label;
                    }
                }
                p(implode("\n", $lines));
            ?></textarea>
		</div>

		<div class="ncvp-admin-field">
			<label class="ncvp-checkbox-label">
				<input type="checkbox" id="ncvpFfmpegEnabled" <?php if ($ffmpegEnabled): ?>checked<?php endif; ?> />
				<span><?php p($l->t('Enable optional in-memory FFmpeg transcoding for legacy video formats')); ?></span>
			</label>
		</div>

		<div class="ncvp-admin-grid">
			<div class="ncvp-admin-field">
				<label for="ncvpFfmpegPath"><?php p($l->t('FFmpeg binary path')); ?></label>
				<input type="text" id="ncvpFfmpegPath" value="<?php p($ffmpegPath); ?>" />
			</div>
			<div class="ncvp-admin-field">
				<label for="ncvpFfprobePath"><?php p($l->t('FFprobe binary path')); ?></label>
				<input type="text" id="ncvpFfprobePath" value="<?php p($ffprobePath); ?>" />
			</div>
		</div>

		<div class="ncvp-admin-grid">
			<div class="ncvp-admin-field">
				<label for="ncvpSessionTtl"><?php p($l->t('Playback session TTL (seconds)')); ?></label>
				<input type="number" id="ncvpSessionTtl" min="30" max="86400" value="<?php p((string) $sessionTtl); ?>" />
			</div>
			<div class="ncvp-admin-field">
				<label for="ncvpMaxConcurrent"><?php p($l->t('Max concurrent sessions per user (0 = unlimited)')); ?></label>
				<input type="number" id="ncvpMaxConcurrent" min="0" max="100" value="<?php p((string) $maxConcurrent); ?>" />
			</div>
		</div>

		<div class="ncvp-admin-actions">
			<button type="submit" id="ncvpAdminSaveBtn" class="button primary">
				<?php p($l->t('Save Settings')); ?>
			</button>
			<span id="ncvpAdminStatus" class="ncvp-admin-status" aria-live="polite"></span>
		</div>
	</form>
</div>
