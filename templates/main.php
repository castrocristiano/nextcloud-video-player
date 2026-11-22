<?php

declare(strict_types=1);

/** @var \OCP\IL10N $l */
/** @var array<string, mixed> $_ */
$appVersion = isset($_['appVersion']) ? (string) $_['appVersion'] : '0.1.0';
?>
<div id="ncvp-app" class="ncvp-app" data-app-version="<?php p($appVersion); ?>">
	<header class="ncvp-topbar">
		<button type="button" class="ncvp-brand" id="brandHome" aria-label="<?php p($l->t('Return to library home')); ?>">
			<span class="ncvp-brand-icon" aria-hidden="true">&#9654;</span>
			<div class="ncvp-brand-text">
				<h1 class="ncvp-brand-title"><?php p($l->t('Nextcloud Video Player')); ?></h1>
				<span class="ncvp-brand-subtitle"><?php p($l->t('Configured Folders • Strictly Read-Only')); ?></span>
			</div>
		</button>

		<div class="ncvp-topbar-controls">
			<div class="ncvp-search-box">
				<input
					type="search"
					id="searchInput"
					placeholder="<?php p($l->t('Search videos in library...')); ?>"
					aria-label="<?php p($l->t('Search videos by name')); ?>"
					autocomplete="off"
				/>
				<button type="button" id="clearSearchBtn" class="ncvp-btn-icon hidden" title="<?php p($l->t('Clear search')); ?>" aria-label="<?php p($l->t('Clear search')); ?>">
					&times;
				</button>
			</div>

			<div class="ncvp-sort-box">
				<label for="sortSelect" class="ncvp-sr-only"><?php p($l->t('Sort by')); ?></label>
				<select id="sortSelect" aria-label="<?php p($l->t('Sort order')); ?>">
					<option value="name_asc"><?php p($l->t('Name (A–Z)')); ?></option>
					<option value="name_desc"><?php p($l->t('Name (Z–A)')); ?></option>
					<option value="date_desc"><?php p($l->t('Newest first')); ?></option>
					<option value="date_asc"><?php p($l->t('Oldest first')); ?></option>
				</select>
			</div>

			<button type="button" id="openSessionsModalBtn" class="ncvp-btn ncvp-btn-secondary" title="<?php p($l->t('View active playback sessions')); ?>">
				<span><?php p($l->t('Active Sessions')); ?></span>
			</button>

			<button type="button" id="openLibraryModalBtn" class="ncvp-btn ncvp-btn-primary" title="<?php p($l->t('Select and manage library folders')); ?>">
				<span><?php p($l->t('Library Folders')); ?></span>
			</button>
		</div>
	</header>

	<main class="ncvp-main">
		<section id="rootsBarSection" class="ncvp-roots-bar" aria-label="<?php p($l->t('Configured library root folders')); ?>">
			<div id="rootsTabs" class="ncvp-roots-tabs"></div>
		</section>

		<nav id="breadcrumbsNav" class="ncvp-breadcrumbs" aria-label="<?php p($l->t('Breadcrumb navigation')); ?>">
			<ol id="breadcrumbsList" class="ncvp-breadcrumbs-list"></ol>
		</nav>

		<div id="alertBanner" class="ncvp-alert hidden" role="alert">
			<span id="alertMessage"></span>
			<button type="button" id="alertRetryBtn" class="ncvp-btn ncvp-btn-sm"><?php p($l->t('Refresh')); ?></button>
		</div>

		<section id="foldersSection" class="ncvp-section hidden" aria-label="<?php p($l->t('Subfolders')); ?>">
			<h2 class="ncvp-section-heading"><?php p($l->t('Folders')); ?> <span id="foldersCount" class="ncvp-badge">0</span></h2>
			<div id="foldersGrid" class="ncvp-folders-grid"></div>
		</section>

		<section id="videosSection" class="ncvp-section hidden" aria-label="<?php p($l->t('Videos')); ?>">
			<h2 id="videosSectionTitle" class="ncvp-section-heading"><?php p($l->t('Videos')); ?> <span id="videosCount" class="ncvp-badge">0</span></h2>
			<div id="videosGrid" class="ncvp-videos-grid"></div>
		</section>

		<section id="emptyState" class="ncvp-empty-state hidden" aria-live="polite">
			<div class="ncvp-empty-card">
				<h3 id="emptyTitle"><?php p($l->t('No videos found')); ?></h3>
				<p id="emptyDescription"><?php p($l->t('Select a folder containing supported videos or adjust your search filter.')); ?></p>
				<div class="ncvp-empty-actions">
					<button type="button" id="emptyAddFoldersBtn" class="ncvp-btn ncvp-btn-primary"><?php p($l->t('Add Folders to Library')); ?></button>
					<button type="button" id="emptyResetSearchBtn" class="ncvp-btn ncvp-btn-secondary hidden"><?php p($l->t('Clear Search')); ?></button>
				</div>
			</div>
		</section>
	</main>

	<!-- Video Player Modal -->
	<div id="playerModal" class="ncvp-modal-overlay hidden">
		<dialog class="ncvp-player-dialog" open aria-labelledby="playerVideoTitle">
			<header class="ncvp-player-header">
				<div class="ncvp-player-title-group">
					<h2 id="playerVideoTitle" class="ncvp-player-title"><?php p($l->t('Video Title')); ?></h2>
					<span id="playerVideoPath" class="ncvp-player-subtitle"></span>
				</div>
				<div class="ncvp-player-header-actions">
					<button type="button" id="prevVideoBtn" class="ncvp-btn ncvp-btn-secondary ncvp-btn-sm" title="<?php p($l->t('Previous video in folder')); ?>">
						&#9664; <?php p($l->t('Previous')); ?>
					</button>
					<button type="button" id="nextVideoBtn" class="ncvp-btn ncvp-btn-secondary ncvp-btn-sm" title="<?php p($l->t('Next video in folder')); ?>">
						<?php p($l->t('Next')); ?> &#9654;
					</button>
					<button type="button" id="closePlayerBtn" class="ncvp-btn ncvp-btn-danger ncvp-btn-sm" title="<?php p($l->t('Close player')); ?>">
						&#10005; <?php p($l->t('Close')); ?>
					</button>
				</div>
			</header>

			<div id="resumeBanner" class="ncvp-resume-banner hidden">
				<span id="resumeBannerText"><?php p($l->t('Resuming playback from where you left off.')); ?></span>
				<button type="button" id="restartFromBeginningBtn" class="ncvp-btn ncvp-btn-sm"><?php p($l->t('Restart from beginning')); ?></button>
			</div>

			<div id="playerErrorBanner" class="ncvp-player-error hidden" role="alert">
				<strong id="playerErrorTitle"><?php p($l->t('Format or codec not supported by this browser.')); ?></strong>
				<p id="playerErrorText"><?php p($l->t('The video file could not be decoded directly by your browser. Ask an administrator to enable FFmpeg transcoding or convert the file to MP4 (H.264/AAC) or WebM.')); ?></p>
				<button type="button" id="errorBackToLibBtn" class="ncvp-btn ncvp-btn-secondary ncvp-btn-sm"><?php p($l->t('Back to Library')); ?></button>
			</div>

			<div class="ncvp-player-stage" id="playerStage">
				<video id="videoElement" preload="metadata" playsinline></video>

				<div id="autoplayOverlay" class="ncvp-autoplay-overlay hidden">
					<div class="ncvp-autoplay-card">
						<p class="ncvp-autoplay-label"><?php p($l->t('Next video in')); ?> <strong id="autoplayCountdown">5</strong>s</p>
						<h3 id="autoplayNextTitle" class="ncvp-autoplay-title"><?php p($l->t('Next video')); ?></h3>
						<div class="ncvp-autoplay-actions">
							<button type="button" id="autoplayPlayNowBtn" class="ncvp-btn ncvp-btn-primary"><?php p($l->t('Play Now')); ?></button>
							<button type="button" id="autoplayCancelBtn" class="ncvp-btn ncvp-btn-secondary"><?php p($l->t('Cancel')); ?></button>
						</div>
					</div>
				</div>
			</div>

			<div class="ncvp-player-bottom-controls" id="playerBottomControls">
				<div class="ncvp-player-seekbar">
					<button type="button" id="playPauseBtn" class="ncvp-btn ncvp-btn-primary ncvp-btn-sm" title="<?php p($l->t('Play / Pause (Space)')); ?>">
						&#9654;
					</button>
					<button type="button" id="seekBackBtn" class="ncvp-btn ncvp-btn-secondary ncvp-btn-sm" title="<?php p($l->t('Rewind 10 seconds (Left Arrow)')); ?>">
						&#8630; -10s
					</button>
					<button type="button" id="seekFwdBtn" class="ncvp-btn ncvp-btn-secondary ncvp-btn-sm" title="<?php p($l->t('Forward 10 seconds (Right Arrow)')); ?>">
						+10s &#8631;
					</button>
					<span id="currentTimeLabel" class="ncvp-seek-time">00:00</span>
					<input
						type="range"
						id="seekSlider"
						class="ncvp-seek-slider"
						min="0"
						max="100"
						step="1"
						value="0"
						aria-label="<?php p($l->t('Video progress bar')); ?>"
					/>
					<span id="totalDurationLabel" class="ncvp-seek-time">00:00</span>
					<div class="ncvp-volume-control">
						<button type="button" id="muteBtn" class="ncvp-btn ncvp-btn-secondary ncvp-btn-sm" title="<?php p($l->t('Mute / Unmute (M)')); ?>">
							&#128266;
						</button>
						<input
							type="range"
							id="volumeSlider"
							class="ncvp-volume-slider"
							min="0"
							max="1"
							step="0.05"
							value="1"
							aria-label="<?php p($l->t('Volume')); ?>"
						/>
					</div>
					<button type="button" id="fullscreenBtn" class="ncvp-btn ncvp-btn-secondary ncvp-btn-sm" title="<?php p($l->t('Fullscreen (F)')); ?>">
						&#x26F6; <?php p($l->t('Fullscreen')); ?>
					</button>
				</div>

				<footer class="ncvp-player-toolbar">
					<div class="ncvp-toolbar-group">
						<label for="speedSelect"><?php p($l->t('Speed:')); ?></label>
						<select id="speedSelect" aria-label="<?php p($l->t('Playback speed')); ?>">
							<option value="0.5">0.5x</option>
							<option value="0.75">0.75x</option>
							<option value="1" selected>1.0x</option>
							<option value="1.25">1.25x</option>
							<option value="1.5">1.5x</option>
							<option value="2">2.0x</option>
						</select>
					</div>

					<div class="ncvp-toolbar-group">
						<label for="subtitleSelect"><?php p($l->t('Subtitles:')); ?></label>
						<select id="subtitleSelect" aria-label="<?php p($l->t('Select local subtitle')); ?>">
							<option value="-1"><?php p($l->t('Disabled')); ?></option>
						</select>
						<div class="ncvp-sub-sync-group" id="subSyncGroup">
							<button type="button" id="subSyncMinusBtn" class="ncvp-btn ncvp-btn-secondary ncvp-btn-sm" title="<?php p($l->t('Advance subtitle (-0.5s)')); ?>">-0.5s</button>
							<span id="subSyncLabel" class="ncvp-seek-time" title="<?php p($l->t('Subtitle synchronization offset')); ?>">0.0s</span>
							<button type="button" id="subSyncPlusBtn" class="ncvp-btn ncvp-btn-secondary ncvp-btn-sm" title="<?php p($l->t('Delay subtitle (+0.5s)')); ?>">+0.5s</button>
						</div>
					</div>

					<div class="ncvp-toolbar-group">
						<label for="audioTrackSelect"><?php p($l->t('Audio:')); ?></label>
						<select id="audioTrackSelect" aria-label="<?php p($l->t('Select audio track')); ?>" disabled>
							<option value="0"><?php p($l->t('Default (1 track)')); ?></option>
						</select>
					</div>

					<div class="ncvp-toolbar-group ncvp-toolbar-toggle">
						<label class="ncvp-checkbox-label">
							<input type="checkbox" id="autoplayNextCheckbox" checked />
							<span><?php p($l->t('Autoplay next video')); ?></span>
						</label>
					</div>
				</footer>
			</div>
		</dialog>
	</div>

	<!-- Library Folder Picker & Management Modal -->
	<div id="libraryModal" class="ncvp-modal-overlay hidden">
		<dialog class="ncvp-library-dialog" open aria-labelledby="libraryModalTitle">
			<header class="ncvp-modal-header">
				<div>
					<h2 id="libraryModalTitle"><?php p($l->t('Manage Library Folders')); ?></h2>
					<p class="ncvp-modal-subtitle"><?php p($l->t('Strictly read-only access • Selected folders are persisted in your Nextcloud account')); ?></p>
				</div>
				<button type="button" id="closeLibraryModalBtn" class="ncvp-btn ncvp-btn-secondary ncvp-btn-sm">
					&#10005; <?php p($l->t('Close')); ?>
				</button>
			</header>

			<div class="ncvp-modal-body">
				<section class="ncvp-library-section">
					<h3 class="ncvp-subsection-title"><?php p($l->t('Active Folders in Library')); ?></h3>
					<ul id="activeRootsList" class="ncvp-active-roots-list"></ul>
				</section>

				<section id="pickerSection" class="ncvp-library-section">
					<div class="ncvp-picker-header">
						<h3 class="ncvp-subsection-title"><?php p($l->t('Select One or More Folders to Add')); ?></h3>
						<button type="button" id="selectCurrentDirBtn" class="ncvp-btn ncvp-btn-secondary ncvp-btn-sm">
							+ <?php p($l->t('Add current open folder')); ?>
						</button>
					</div>

					<nav class="ncvp-picker-breadcrumbs" aria-label="<?php p($l->t('Folder picker navigation')); ?>">
						<ol id="pickerBreadcrumbsList" class="ncvp-breadcrumbs-list"></ol>
					</nav>

					<div id="pickerStatusMsg" class="ncvp-picker-status hidden"></div>

					<ul id="pickerDirsList" class="ncvp-picker-dirs-list"></ul>

					<div class="ncvp-picker-footer">
						<span id="pickerSelectedCount" class="ncvp-picker-count"><?php p($l->t('0 folders selected')); ?></span>
						<button type="button" id="addSelectedDirsBtn" class="ncvp-btn ncvp-btn-primary" disabled>
							<?php p($l->t('Add Selected Folders')); ?>
						</button>
					</div>
				</section>
			</div>
		</dialog>
	</div>

	<!-- Active Playback Sessions Modal -->
	<div id="sessionsModal" class="ncvp-modal-overlay hidden">
		<dialog class="ncvp-sessions-dialog" open aria-labelledby="sessionsModalTitle">
			<header class="ncvp-modal-header">
				<div>
					<h2 id="sessionsModalTitle"><?php p($l->t('Active Playback Sessions')); ?></h2>
					<p class="ncvp-modal-subtitle"><?php p($l->t('Manage your active video streaming sessions across devices')); ?></p>
				</div>
				<button type="button" id="closeSessionsModalBtn" class="ncvp-btn ncvp-btn-secondary ncvp-btn-sm">
					&#10005; <?php p($l->t('Close')); ?>
				</button>
			</header>
			<div class="ncvp-modal-body">
				<div id="sessionsStatusMsg" class="ncvp-picker-status hidden"></div>
				<ul id="activeSessionsList" class="ncvp-active-roots-list"></ul>
			</div>
		</dialog>
	</div>
</div>
