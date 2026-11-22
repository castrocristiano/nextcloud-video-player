import { apiGet, buildAppUrl } from "./api.js";
import { formatDuration } from "./formatters.js";
import { t } from "./i18n.js";
import { getVideoProgress, recordLocalVideoProgress, savePreferencesToServer } from "./state.js";

export const FULLSCREEN_HIDE_DELAY_MS = 2500;
export const AUTOPLAY_COUNTDOWN_SECONDS = 5;
export const MIN_SUBTITLE_SCALE = 0.75;
export const MAX_SUBTITLE_SCALE = 2.0;
export const SUBTITLE_SCALE_STEP = 0.25;
export const TRANSCODE_SEEK_DEBOUNCE_MS = 300;
export const TRANSCODE_EXTENSIONS = new Set([
  "avi",
  "mkv",
  "wmv",
  "flv",
  "mpg",
  "mpeg",
  "rmvb",
  "3gp",
  "ts",
  "m2ts",
]);

function requiresImmediateTranscode(filename) {
  const parts = String(filename || "").toLowerCase().split(".");
  if (parts.length < 2) {
    return false;
  }
  const ext = parts[parts.length - 1];
  return TRANSCODE_EXTENSIONS.has(ext);
}

export class VideoPlayerController {
  constructor(dom, state, sessionManager, { onCatalogRefresh = null } = {}) {
    this.dom = dom;
    this.state = state;
    this.sessionManager = sessionManager;
    this.onCatalogRefresh = onCatalogRefresh;
    this.fsHideTimer = null;
    this.autoplayTimer = null;
    this.seekDebounceTimer = null;
    this.pendingSeekTarget = null;
    this.autoplayRemaining = AUTOPLAY_COUNTDOWN_SECONDS;
  }

  setLoadingSpinner(visible) {
    if (!this.dom.playerLoadingSpinner) {
      return;
    }
    if (visible) {
      this.dom.playerLoadingSpinner.classList.remove("hidden");
    } else {
      this.dom.playerLoadingSpinner.classList.add("hidden");
    }
  }

  applySubtitleScale(scale) {
    const clamped =
      Math.round(
        Math.min(MAX_SUBTITLE_SCALE, Math.max(MIN_SUBTITLE_SCALE, Number(scale) || 1.0)) *
          100
      ) / 100;
    this.state.prefs.subtitleScale = clamped;
    if (this.dom.subtitleOverlay) {
      this.dom.subtitleOverlay.style.setProperty(
        "--ncvp-subtitle-scale",
        String(clamped)
      );
    }
    if (this.dom.subSizeLabel) {
      this.dom.subSizeLabel.textContent = `${Math.round(clamped * 100)}%`;
    }
    return clamped;
  }

  adjustSubtitleSize(delta) {
    const current = Number(this.state.prefs?.subtitleScale) || 1.0;
    const next = this.applySubtitleScale(current + delta);
    savePreferencesToServer(this.state, { subtitleScale: next });
  }

  bindEvents() {
    const { dom, state } = this;
    if (!dom.videoElement) {
      return;
    }

    dom.playPauseBtn?.addEventListener("click", () => this.togglePlayPause());
    dom.seekBackBtn?.addEventListener("click", () => this.seekRelative(-10));
    dom.seekFwdBtn?.addEventListener("click", () => this.seekRelative(10));
    dom.muteBtn?.addEventListener("click", () => this.toggleMute());
    dom.fullscreenBtn?.addEventListener("click", () => this.toggleFullscreen());
    dom.closePlayerBtn?.addEventListener("click", () => this.closePlayer());
    dom.errorBackToLibBtn?.addEventListener("click", () => this.closePlayer());
    dom.prevVideoBtn?.addEventListener("click", () =>
      this.playAdjacentVideo(-1)
    );
    dom.nextVideoBtn?.addEventListener("click", () =>
      this.playAdjacentVideo(1)
    );

    dom.restartFromBeginningBtn?.addEventListener("click", () => {
      dom.resumeBanner?.classList.add("hidden");
      this.seekToSeconds(0);
    });

    dom.speedSelect?.addEventListener("change", () => {
      const rate = Number(dom.speedSelect.value) || 1.0;
      dom.videoElement.playbackRate = rate;
      savePreferencesToServer(state, { playbackRate: rate });
    });

    dom.volumeSlider?.addEventListener("input", () => {
      const vol = Math.min(1, Math.max(0, Number(dom.volumeSlider.value)));
      dom.videoElement.volume = vol;
      dom.videoElement.muted = vol === 0;
      savePreferencesToServer(state, {
        volume: vol,
        muted: dom.videoElement.muted,
      });
    });

    dom.autoplayNextCheckbox?.addEventListener("change", () => {
      savePreferencesToServer(state, {
        autoPlayNext: Boolean(dom.autoplayNextCheckbox.checked),
      });
    });

    dom.subtitleSelect?.addEventListener("change", () => {
      const idx = Number(dom.subtitleSelect.value);
      this.applySelectedSubtitleTrack(idx);
      const subs = state.currentVideo?.subtitles || [];
      savePreferencesToServer(state, {
        preferredSubtitle: idx >= 0 && subs[idx] ? subs[idx].lang || "default" : "off",
      });
    });

    dom.subSyncMinusBtn?.addEventListener("click", () => {
      this.adjustSubtitleSyncOffset(-0.5);
    });

    dom.subSyncPlusBtn?.addEventListener("click", () => {
      this.adjustSubtitleSyncOffset(0.5);
    });

    dom.subSizeMinusBtn?.addEventListener("click", () => {
      this.adjustSubtitleSize(-SUBTITLE_SCALE_STEP);
    });

    dom.subSizePlusBtn?.addEventListener("click", () => {
      this.adjustSubtitleSize(SUBTITLE_SCALE_STEP);
    });

    this.applySubtitleScale(state.prefs?.subtitleScale ?? 1.0);

    dom.audioTrackSelect?.addEventListener("change", () => {
      this.applySelectedAudioTrack(Number(dom.audioTrackSelect.value));
    });

    dom.seekSlider?.addEventListener("input", () => {
      state.isDraggingSeek = true;
      const target = Number(dom.seekSlider.value) || 0;
      if (dom.currentTimeLabel) {
        dom.currentTimeLabel.textContent = formatDuration(target);
      }
    });

    dom.seekSlider?.addEventListener("change", () => {
      const target = Number(dom.seekSlider.value) || 0;
      state.isDraggingSeek = false;
      this.seekToSeconds(target);
    });

    dom.autoplayPlayNowBtn?.addEventListener("click", () => {
      this.cancelAutoplayCountdown();
      this.playAdjacentVideo(1);
    });

    dom.autoplayCancelBtn?.addEventListener("click", () => {
      this.cancelAutoplayCountdown();
    });

    dom.videoElement.addEventListener("loadedmetadata", () => {
      const dur = Number(dom.videoElement.duration);
      if (Number.isFinite(dur) && dur > 0 && !state.isTranscodedStream) {
        this.applyTotalDuration(dur);
      } else if (
        Number.isFinite(dur) &&
        dur > 0 &&
        state.totalDuration <= 0
      ) {
        this.applyTotalDuration(dur);
      }
      if (dom.subtitleSelect) {
        this.applySelectedSubtitleTrack(Number(dom.subtitleSelect.value));
      }
      this.populateAudioTracks();
    });

    dom.videoElement.addEventListener("durationchange", () => {
      const dur = Number(dom.videoElement.duration);
      if (Number.isFinite(dur) && dur > 0 && !state.isTranscodedStream) {
        this.applyTotalDuration(dur);
      }
    });

    dom.videoElement.addEventListener("dblclick", () => {
      this.toggleFullscreen();
    });

    dom.videoElement.addEventListener("timeupdate", () => {
      if (state.isDraggingSeek) {
        return;
      }
      const cur =
        (Number(dom.videoElement.currentTime) || 0) +
        (state.transcodeBaseOffset || 0);
      if (dom.currentTimeLabel) {
        dom.currentTimeLabel.textContent = formatDuration(cur);
      }
      if (dom.seekSlider) {
        dom.seekSlider.value = String(Math.floor(cur));
      }
      if (state.currentVideo && state.totalDuration > 0) {
        recordLocalVideoProgress(
          state,
          state.currentVideo,
          cur,
          state.totalDuration,
          false
        );
      }
    });

    dom.videoElement.addEventListener("loadstart", () => {
      this.setLoadingSpinner(true);
    });

    dom.videoElement.addEventListener("waiting", () => {
      this.setLoadingSpinner(true);
    });

    dom.videoElement.addEventListener("seeking", () => {
      this.setLoadingSpinner(true);
    });

    dom.videoElement.addEventListener("canplay", () => {
      this.setLoadingSpinner(false);
    });

    dom.videoElement.addEventListener("playing", () => {
      this.setLoadingSpinner(false);
    });

    dom.videoElement.addEventListener("seeked", () => {
      this.setLoadingSpinner(false);
    });

    dom.videoElement.addEventListener("ended", () => {
      this.setLoadingSpinner(false);
      this.handleVideoEnded();
    });

    dom.videoElement.addEventListener("error", () => {
      this.setLoadingSpinner(false);
      if (
        state.capabilities?.ffmpegEnabled &&
        !state.isTranscodedStream &&
        !state.triedTranscodeFallback &&
        state.currentVideo
      ) {
        state.triedTranscodeFallback = true;
        this.switchToTranscodedStream(state.currentVideo, 0);
        return;
      }
      dom.playerErrorBanner?.classList.remove("hidden");
    });

    // Fullscreen mouse movement auto-hide
    dom.playerDialog?.addEventListener("mousemove", () =>
      this.scheduleFullscreenAutoHide()
    );
    document.addEventListener("fullscreenchange", () =>
      this.handleFullscreenChange()
    );
    document.addEventListener("keydown", (e) => this.handleKeyDown(e));
  }

  async openVideoByIndex(index) {
    const { dom, state } = this;
    const videos = state.videos || [];
    if (index < 0 || index >= videos.length) {
      return;
    }

    this.cancelAutoplayCountdown();
    this.cancelPendingSeek();
    this.setLoadingSpinner(true);
    this.applySubtitleScale(state.prefs?.subtitleScale ?? 1.0);
    const video = videos[index];
    state.currentVideoIndex = index;
    state.currentVideo = video;
    state.transcodeBaseOffset = 0;
    state.subtitleSyncOffset = 0;
    state.totalDuration = 0;
    state.triedTranscodeFallback = false;

    if (dom.subSyncLabel) {
      dom.subSyncLabel.textContent = "0.0s";
    }

    if (dom.currentTimeLabel) {
      dom.currentTimeLabel.textContent = "00:00";
    }
    if (dom.totalDurationLabel) {
      dom.totalDurationLabel.textContent = "00:00";
    }
    if (dom.seekSlider) {
      dom.seekSlider.value = "0";
      dom.seekSlider.max = "100";
    }

    const useTranscodeImmediately =
      Boolean(state.capabilities?.ffmpegEnabled) &&
      requiresImmediateTranscode(video.name);
    state.isTranscodedStream = useTranscodeImmediately;

    const savedProgress = getVideoProgress(state, video);
    if (savedProgress && Number(savedProgress.duration) > 0) {
      this.applyTotalDuration(Number(savedProgress.duration));
    }

    dom.playerErrorBanner?.classList.add("hidden");
    dom.resumeBanner?.classList.add("hidden");
    dom.playerModal?.classList.remove("hidden");

    if (dom.playerVideoTitle) {
      dom.playerVideoTitle.textContent = video.name;
    }
    if (dom.playerVideoPath) {
      dom.playerVideoPath.textContent = `${video.rootLabel || ""} • ${video.relativePath}`;
    }
    if (dom.prevVideoBtn) {
      dom.prevVideoBtn.disabled = index <= 0;
    }
    if (dom.nextVideoBtn) {
      dom.nextVideoBtn.disabled = index >= videos.length - 1;
    }

    if (state.capabilities?.ffmpegEnabled) {
      this.probeVideoDuration(video);
    }

    // Start tracked playback session on the server
    const streamMode = useTranscodeImmediately ? "transcode" : "direct";
    const sessionResp = await this.sessionManager.startSession(video, streamMode);
    const sessionToken = sessionResp.sessionToken;

    let initialStartOffset = 0;
    if (
      savedProgress &&
      !savedProgress.completed &&
      savedProgress.currentTime > 3
    ) {
      if (useTranscodeImmediately) {
        initialStartOffset = Math.floor(savedProgress.currentTime);
        state.transcodeBaseOffset = initialStartOffset;
      }
      if (dom.resumeBanner && dom.resumeBannerText) {
        dom.resumeBannerText.textContent = t(
          "Resuming playback from {time}.",
          { time: formatDuration(savedProgress.currentTime) }
        );
        dom.resumeBanner.classList.remove("hidden");
      }
    }

    const streamParams = {
      path: video.relativePath,
      sessionToken,
    };
    if (useTranscodeImmediately) {
      streamParams.transcode = "1";
      if (initialStartOffset > 0) {
        streamParams.start = String(initialStartOffset);
      }
    }

    const streamUrl = buildAppUrl(
      `/api/v1/stream/${encodeURIComponent(video.rootId)}`,
      streamParams
    );

    this.populateSubtitleTracks(video, initialStartOffset);

    dom.videoElement.src = streamUrl;
    dom.videoElement.playbackRate = state.prefs.playbackRate || 1.0;
    dom.videoElement.volume = state.prefs.volume ?? 1.0;
    dom.videoElement.muted = Boolean(state.prefs.muted);

    if (
      savedProgress &&
      !savedProgress.completed &&
      savedProgress.currentTime > 3 &&
      !useTranscodeImmediately
    ) {
      dom.videoElement.currentTime = savedProgress.currentTime;
    }

    this.sessionManager.startHeartbeatLoop(() => ({
      currentTime:
        (Number(dom.videoElement.currentTime) || 0) +
        (state.transcodeBaseOffset || 0),
      duration:
        state.totalDuration || Number(dom.videoElement.duration) || 0,
      status: dom.videoElement.paused ? "paused" : "active",
      completed: false,
    }));

    try {
      await dom.videoElement.play();
      if (dom.playPauseBtn) {
        dom.playPauseBtn.textContent = "❚❚";
      }
    } catch {
      if (dom.playPauseBtn) {
        dom.playPauseBtn.textContent = "▶";
      }
    }
  }

  applyTotalDuration(durationSeconds) {
    const dur = Number(durationSeconds);
    if (!Number.isFinite(dur) || dur <= 0) {
      return;
    }
    this.state.totalDuration = dur;
    if (this.dom.seekSlider) {
      this.dom.seekSlider.max = String(Math.floor(dur));
    }
    if (this.dom.totalDurationLabel) {
      this.dom.totalDurationLabel.textContent = formatDuration(dur);
    }
  }

  async probeVideoDuration(video) {
    try {
      const info = await apiGet(
        `/api/v1/probe/${encodeURIComponent(video.rootId)}`,
        { path: video.relativePath }
      );
      if (
        this.state.currentVideo &&
        this.state.currentVideo.relativePath === video.relativePath &&
        Number(info?.duration) > 0
      ) {
        this.applyTotalDuration(Number(info.duration));
      }
    } catch {
      // Fallback to loadedmetadata duration
    }
  }

  async switchToTranscodedStream(video, startOffsetSeconds = 0) {
    const { dom, state } = this;
    state.isTranscodedStream = true;
    state.transcodeBaseOffset = Math.max(0, Math.floor(Number(startOffsetSeconds) || 0));
    dom.playerErrorBanner?.classList.add("hidden");

    if (state.totalDuration <= 0 && state.capabilities?.ffmpegEnabled) {
      this.probeVideoDuration(video);
    }

    const sessionResp = await this.sessionManager.startSession(video, "transcode");
    const streamUrl = buildAppUrl(
      `/api/v1/stream/${encodeURIComponent(video.rootId)}`,
      {
        path: video.relativePath,
        sessionToken: sessionResp.sessionToken,
        transcode: "1",
        ...(state.transcodeBaseOffset > 0
          ? { start: String(state.transcodeBaseOffset) }
          : {}),
      }
    );

    this.populateSubtitleTracks(video, state.transcodeBaseOffset);

    dom.videoElement.src = streamUrl;
    dom.videoElement.playbackRate = state.prefs.playbackRate || 1.0;
    try {
      await dom.videoElement.play();
      if (dom.playPauseBtn) {
        dom.playPauseBtn.textContent = "❚❚";
      }
    } catch {
      if (dom.playPauseBtn) {
        dom.playPauseBtn.textContent = "▶";
      }
    }
  }

  populateSubtitleTracks(video, offsetSeconds = 0) {
    const { dom } = this;
    if (!dom.videoElement || !dom.subtitleSelect) {
      return;
    }
    const previousSelection =
      dom.subtitleSelect.options.length > 1
        ? Number(dom.subtitleSelect.value)
        : null;
    dom.videoElement.querySelectorAll("track").forEach((tr) => tr.remove());
    dom.subtitleSelect.innerHTML = "";

    const offOpt = document.createElement("option");
    offOpt.value = "-1";
    offOpt.textContent = t("Disabled");
    dom.subtitleSelect.appendChild(offOpt);

    const subs = video.subtitles || [];
    let autoSelectIdx = -1;
    if (
      previousSelection !== null &&
      Number.isFinite(previousSelection) &&
      previousSelection >= -1 &&
      previousSelection < subs.length
    ) {
      autoSelectIdx = previousSelection;
    } else if (subs.length > 0 && this.state.prefs?.preferredSubtitle !== "off") {
      const pref = this.state.prefs?.preferredSubtitle;
      const matched = pref
        ? subs.findIndex((s) => s.lang === pref || s.label === pref)
        : -1;
      autoSelectIdx = matched >= 0 ? matched : 0;
    }

    const safeOffset = Math.max(0, Math.floor(Number(offsetSeconds) || 0));
    const syncDelay = Number(this.state.subtitleSyncOffset) || 0;

    subs.forEach((sub, idx) => {
      const track = document.createElement("track");
      track.kind = "subtitles";
      track.label = sub.label || sub.lang || `Subtitle ${idx + 1}`;
      track.srclang = sub.lang && sub.lang !== "default" ? sub.lang : "pt";
      track.src = buildAppUrl(
        `/api/v1/subtitles/${encodeURIComponent(video.rootId)}`,
        {
          path: sub.relativePath,
          ...(safeOffset > 0 ? { offset: String(safeOffset) } : {}),
          ...(Math.abs(syncDelay) >= 0.01 ? { delay: syncDelay.toFixed(2) } : {}),
        }
      );
      if (idx === autoSelectIdx) {
        track.default = true;
      }
      dom.videoElement.appendChild(track);

      const opt = document.createElement("option");
      opt.value = String(idx);
      opt.textContent = track.label;
      if (idx === autoSelectIdx) {
        opt.selected = true;
      }
      dom.subtitleSelect.appendChild(opt);
    });

    if (autoSelectIdx >= 0) {
      dom.subtitleSelect.value = String(autoSelectIdx);
      setTimeout(() => {
        this.applySelectedSubtitleTrack(autoSelectIdx);
      }, 50);
    }
  }

  applySelectedSubtitleTrack(selectedIdx) {
    const tracks = this.dom.videoElement?.textTracks;
    if (!tracks) {
      this.renderSubtitleOverlayText("");
      return;
    }
    const useCustomOverlay = Boolean(this.dom.subtitleOverlay);
    for (let i = 0; i < tracks.length; i++) {
      const tr = tracks[i];
      tr.oncuechange = null;
      if (i === selectedIdx) {
        tr.mode = useCustomOverlay ? "hidden" : "showing";
        if (useCustomOverlay) {
          const updateCues = () => {
            const active = tr.activeCues;
            if (!active || active.length === 0) {
              this.renderSubtitleOverlayText("");
              return;
            }
            const lines = [];
            for (let c = 0; c < active.length; c++) {
              const text = String(active[c].text || "")
                .replace(/<\/?[^>]+(>|$)/g, "")
                .trim();
              if (text) {
                lines.push(text);
              }
            }
            this.renderSubtitleOverlayText(lines.join("\n"));
          };
          tr.oncuechange = updateCues;
          updateCues();
        }
      } else {
        tr.mode = "disabled";
      }
    }
    if (selectedIdx < 0 || selectedIdx >= tracks.length) {
      this.renderSubtitleOverlayText("");
    }
  }

  renderSubtitleOverlayText(text) {
    const overlay = this.dom.subtitleOverlay;
    if (!overlay) {
      return;
    }
    overlay.innerHTML = "";
    const cleaned = String(text || "").trim();
    if (!cleaned) {
      overlay.classList.add("hidden");
      return;
    }
    const span = document.createElement("span");
    span.className = "ncvp-subtitle-line";
    span.textContent = cleaned;
    overlay.appendChild(span);
    overlay.classList.remove("hidden");
  }

  adjustSubtitleSyncOffset(deltaSeconds) {
    const { dom, state } = this;
    if (!state.currentVideo) {
      return;
    }
    const next = Math.round(((Number(state.subtitleSyncOffset) || 0) + deltaSeconds) * 10) / 10;
    state.subtitleSyncOffset = next;
    if (dom.subSyncLabel) {
      const sign = next > 0 ? "+" : "";
      dom.subSyncLabel.textContent = `${sign}${next.toFixed(1)}s`;
    }
    this.populateSubtitleTracks(
      state.currentVideo,
      state.isTranscodedStream ? state.transcodeBaseOffset : 0
    );
  }

  populateAudioTracks() {
    const { dom } = this;
    if (!dom.audioTrackSelect || !dom.videoElement) {
      return;
    }
    dom.audioTrackSelect.innerHTML = "";
    const audioTracks = dom.videoElement.audioTracks;

    if (!audioTracks || audioTracks.length <= 1) {
      const opt = document.createElement("option");
      opt.value = "0";
      opt.textContent = t("Default (1 track)");
      dom.audioTrackSelect.appendChild(opt);
      dom.audioTrackSelect.disabled = true;
      return;
    }

    dom.audioTrackSelect.disabled = false;
    for (let i = 0; i < audioTracks.length; i++) {
      const tr = audioTracks[i];
      const opt = document.createElement("option");
      opt.value = String(i);
      opt.textContent =
        tr.label || tr.language || t("Track {number}", { number: i + 1 });
      if (tr.enabled) {
        opt.selected = true;
      }
      dom.audioTrackSelect.appendChild(opt);
    }
  }

  applySelectedAudioTrack(selectedIdx) {
    const audioTracks = this.dom.videoElement?.audioTracks;
    if (!audioTracks) {
      return;
    }
    for (let i = 0; i < audioTracks.length; i++) {
      audioTracks[i].enabled = i === selectedIdx;
    }
  }

  togglePlayPause() {
    const { videoElement, playPauseBtn } = this.dom;
    if (!videoElement) {
      return;
    }
    if (videoElement.paused) {
      videoElement.play().catch(() => {});
      if (playPauseBtn) {
        playPauseBtn.textContent = "❚❚";
      }
    } else {
      videoElement.pause();
      if (playPauseBtn) {
        playPauseBtn.textContent = "▶";
      }
      this.sessionManager
        .sendHeartbeat({
          currentTime: Number(videoElement.currentTime) || 0,
          duration:
            this.state.totalDuration || Number(videoElement.duration) || 0,
          status: "paused",
        })
        .catch(() => {});
    }
  }

  cancelPendingSeek() {
    if (this.seekDebounceTimer) {
      clearTimeout(this.seekDebounceTimer);
      this.seekDebounceTimer = null;
    }
    this.pendingSeekTarget = null;
  }

  seekRelative(deltaSeconds) {
    const baseCur =
      this.pendingSeekTarget !== null
        ? this.pendingSeekTarget
        : (Number(this.dom.videoElement?.currentTime) || 0) +
          (this.state.transcodeBaseOffset || 0);
    this.seekToSeconds(Math.max(0, baseCur + deltaSeconds));
  }

  seekToSeconds(targetSeconds) {
    if (!this.dom.videoElement) {
      return;
    }
    const maxDur = this.state.totalDuration;
    let clamped = Math.max(0, Number(targetSeconds) || 0);
    if (Number.isFinite(maxDur) && maxDur > 0) {
      clamped = Math.min(maxDur, clamped);
    }

    if (this.dom.currentTimeLabel) {
      this.dom.currentTimeLabel.textContent = formatDuration(clamped);
    }
    if (this.dom.seekSlider) {
      this.dom.seekSlider.value = String(Math.floor(clamped));
    }

    if (this.state.isTranscodedStream && this.state.currentVideo) {
      const localTarget = clamped - (this.state.transcodeBaseOffset || 0);
      if (this.isBufferedLocalTime(localTarget)) {
        this.cancelPendingSeek();
        this.setLoadingSpinner(false);
        this.dom.videoElement.currentTime = localTarget;
        return;
      }
      this.pendingSeekTarget = clamped;
      this.setLoadingSpinner(true);
      if (this.seekDebounceTimer) {
        clearTimeout(this.seekDebounceTimer);
      }
      const targetVideo = this.state.currentVideo;
      this.seekDebounceTimer = setTimeout(() => {
        this.seekDebounceTimer = null;
        const finalTarget = this.pendingSeekTarget ?? clamped;
        this.pendingSeekTarget = null;
        this.switchToTranscodedStream(targetVideo, finalTarget);
      }, TRANSCODE_SEEK_DEBOUNCE_MS);
      return;
    }
    this.dom.videoElement.currentTime = clamped;
  }

  isBufferedLocalTime(localSeconds) {
    const video = this.dom.videoElement;
    if (!video || !Number.isFinite(localSeconds) || localSeconds < 0) {
      return false;
    }
    const buffered = video.buffered;
    if (!buffered || typeof buffered.length !== "number") {
      return false;
    }
    for (let i = 0; i < buffered.length; i++) {
      const start = buffered.start(i);
      const end = buffered.end(i);
      if (localSeconds >= start && localSeconds <= Math.max(start, end - 0.5)) {
        return true;
      }
    }
    return false;
  }

  toggleMute() {
    if (!this.dom.videoElement) {
      return;
    }
    this.dom.videoElement.muted = !this.dom.videoElement.muted;
    savePreferencesToServer(this.state, {
      muted: this.dom.videoElement.muted,
    });
  }

  toggleFullscreen() {
    const dialog = this.dom.playerDialog;
    const modal = this.dom.playerModal;
    if (!dialog) {
      return;
    }
    const fsTarget = modal || dialog;
    const isCurrentlyFs =
      Boolean(document.fullscreenElement) ||
      dialog.classList.contains("is-fullscreen");

    if (isCurrentlyFs) {
      if (document.fullscreenElement) {
        document.exitFullscreen?.().catch(() => {});
      }
      dialog.classList.remove("is-fullscreen", "fs-controls-hidden");
      modal?.classList.remove("is-fullscreen");
    } else {
      dialog.classList.add("is-fullscreen");
      modal?.classList.add("is-fullscreen");
      if (typeof fsTarget.requestFullscreen === "function") {
        fsTarget.requestFullscreen().catch(() => {});
      } else if (typeof dialog.requestFullscreen === "function") {
        dialog.requestFullscreen().catch(() => {});
      }
      this.scheduleFullscreenAutoHide();
    }
  }

  handleFullscreenChange() {
    const dialog = this.dom.playerDialog;
    const modal = this.dom.playerModal;
    if (!dialog) {
      return;
    }
    if (
      document.fullscreenElement === dialog ||
      (modal && document.fullscreenElement === modal)
    ) {
      dialog.classList.add("is-fullscreen");
      modal?.classList.add("is-fullscreen");
      this.scheduleFullscreenAutoHide();
    } else if (!document.fullscreenElement) {
      if (this.fsHideTimer) {
        clearTimeout(this.fsHideTimer);
        this.fsHideTimer = null;
      }
      dialog.classList.remove("is-fullscreen", "fs-controls-hidden");
      modal?.classList.remove("is-fullscreen");
    }
  }

  scheduleFullscreenAutoHide() {
    const dialog = this.dom.playerDialog;
    if (!dialog) {
      return;
    }
    dialog.classList.remove("fs-controls-hidden");
    if (this.fsHideTimer) {
      clearTimeout(this.fsHideTimer);
    }
    const isFs =
      document.fullscreenElement === dialog ||
      dialog.classList.contains("is-fullscreen");
    if (!isFs) {
      return;
    }
    this.fsHideTimer = setTimeout(() => {
      if (!this.dom.videoElement?.paused) {
        dialog.classList.add("fs-controls-hidden");
      }
    }, FULLSCREEN_HIDE_DELAY_MS);
  }

  async handleVideoEnded() {
    const { dom, state } = this;
    const dur = state.totalDuration || Number(dom.videoElement?.duration) || 0;
    await this.sessionManager.closeCurrentSession({
      currentTime: dur,
      duration: dur,
      completed: true,
    });

    if (typeof this.onCatalogRefresh === "function") {
      this.onCatalogRefresh();
    }

    const nextIdx = state.currentVideoIndex + 1;
    if (state.prefs.autoPlayNext && nextIdx < (state.videos?.length || 0)) {
      this.startAutoplayCountdown(state.videos[nextIdx]);
    }
  }

  startAutoplayCountdown(nextVideo) {
    const { dom } = this;
    this.cancelAutoplayCountdown();
    this.autoplayRemaining = AUTOPLAY_COUNTDOWN_SECONDS;

    if (dom.autoplayNextTitle) {
      dom.autoplayNextTitle.textContent = nextVideo.name;
    }
    if (dom.autoplayCountdown) {
      dom.autoplayCountdown.textContent = String(this.autoplayRemaining);
    }
    dom.autoplayOverlay?.classList.remove("hidden");

    this.autoplayTimer = setInterval(() => {
      this.autoplayRemaining -= 1;
      if (dom.autoplayCountdown) {
        dom.autoplayCountdown.textContent = String(
          Math.max(0, this.autoplayRemaining)
        );
      }
      if (this.autoplayRemaining <= 0) {
        this.cancelAutoplayCountdown();
        this.playAdjacentVideo(1);
      }
    }, 1000);
  }

  cancelAutoplayCountdown() {
    if (this.autoplayTimer) {
      clearInterval(this.autoplayTimer);
      this.autoplayTimer = null;
    }
    this.dom.autoplayOverlay?.classList.add("hidden");
  }

  playAdjacentVideo(step) {
    const nextIdx = this.state.currentVideoIndex + step;
    if (nextIdx >= 0 && nextIdx < (this.state.videos?.length || 0)) {
      this.openVideoByIndex(nextIdx);
    }
  }

  async closePlayer() {
    this.cancelAutoplayCountdown();
    this.cancelPendingSeek();
    this.setLoadingSpinner(false);
    const { dom, state } = this;
    const cur = Number(dom.videoElement?.currentTime) || 0;
    const dur = state.totalDuration || Number(dom.videoElement?.duration) || 0;

    if (dom.videoElement) {
      dom.videoElement.pause();
      dom.videoElement.removeAttribute("src");
    }
    this.renderSubtitleOverlayText("");
    dom.playerModal?.classList.add("hidden");

    await this.sessionManager.closeCurrentSession({
      currentTime: cur,
      duration: dur,
      completed: false,
    });

    if (typeof this.onCatalogRefresh === "function") {
      this.onCatalogRefresh();
    }
  }

  handleKeyDown(event) {
    if (this.dom.playerModal?.classList.contains("hidden")) {
      return;
    }
    const tag = event.target?.tagName?.toLowerCase();
    if (tag === "input" || tag === "select" || tag === "textarea") {
      return;
    }

    switch (event.key) {
      case " ":
      case "k":
      case "K":
        event.preventDefault();
        this.togglePlayPause();
        break;
      case "ArrowLeft":
        event.preventDefault();
        this.seekRelative(-10);
        break;
      case "ArrowRight":
        event.preventDefault();
        this.seekRelative(10);
        break;
      case "m":
      case "M":
        event.preventDefault();
        this.toggleMute();
        break;
      case "f":
      case "F":
        event.preventDefault();
        this.toggleFullscreen();
        break;
      case "Escape":
        if (!document.fullscreenElement) {
          event.preventDefault();
          this.closePlayer();
        }
        break;
      default:
        break;
    }
  }
}
