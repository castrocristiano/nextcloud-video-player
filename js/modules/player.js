import { buildAppUrl } from "./api.js";
import { formatDuration } from "./formatters.js";
import { t } from "./i18n.js";
import { getVideoProgress, recordLocalVideoProgress, savePreferencesToServer } from "./state.js";

export const FULLSCREEN_HIDE_DELAY_MS = 2500;
export const AUTOPLAY_COUNTDOWN_SECONDS = 5;
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
    this.autoplayRemaining = AUTOPLAY_COUNTDOWN_SECONDS;
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
      this.applySelectedSubtitleTrack(Number(dom.subtitleSelect.value));
    });

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
      if (Number.isFinite(dur) && dur > 0) {
        state.totalDuration = dur;
        if (dom.seekSlider) {
          dom.seekSlider.max = String(Math.floor(dur));
        }
        if (dom.totalDurationLabel) {
          dom.totalDurationLabel.textContent = formatDuration(dur);
        }
      }
      this.populateAudioTracks();
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

    dom.videoElement.addEventListener("ended", () => {
      this.handleVideoEnded();
    });

    dom.videoElement.addEventListener("error", () => {
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
    const video = videos[index];
    state.currentVideoIndex = index;
    state.currentVideo = video;
    state.transcodeBaseOffset = 0;
    state.totalDuration = 0;
    state.triedTranscodeFallback = false;

    const useTranscodeImmediately =
      Boolean(state.capabilities?.ffmpegEnabled) &&
      requiresImmediateTranscode(video.name);
    state.isTranscodedStream = useTranscodeImmediately;

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

    // Start tracked playback session on the server
    const streamMode = useTranscodeImmediately ? "transcode" : "direct";
    const sessionResp = await this.sessionManager.startSession(video, streamMode);
    const sessionToken = sessionResp.sessionToken;

    const streamParams = {
      path: video.relativePath,
      sessionToken,
    };
    if (useTranscodeImmediately) {
      streamParams.transcode = "1";
    }

    const streamUrl = buildAppUrl(
      `/api/v1/stream/${encodeURIComponent(video.rootId)}`,
      streamParams
    );

    this.populateSubtitleTracks(video);

    dom.videoElement.src = streamUrl;
    dom.videoElement.playbackRate = state.prefs.playbackRate || 1.0;
    dom.videoElement.volume = state.prefs.volume ?? 1.0;
    dom.videoElement.muted = Boolean(state.prefs.muted);

    const savedProgress = getVideoProgress(state, video);
    if (
      savedProgress &&
      !savedProgress.completed &&
      savedProgress.currentTime > 3 &&
      !useTranscodeImmediately
    ) {
      dom.videoElement.currentTime = savedProgress.currentTime;
      if (dom.resumeBanner && dom.resumeBannerText) {
        dom.resumeBannerText.textContent = t(
          "Resuming playback from {time}.",
          { time: formatDuration(savedProgress.currentTime) }
        );
        dom.resumeBanner.classList.remove("hidden");
      }
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

  async switchToTranscodedStream(video, startOffsetSeconds = 0) {
    const { dom, state } = this;
    state.isTranscodedStream = true;
    state.transcodeBaseOffset = Math.max(0, Number(startOffsetSeconds) || 0);
    dom.playerErrorBanner?.classList.add("hidden");

    const sessionResp = await this.sessionManager.startSession(video, "transcode");
    const streamUrl = buildAppUrl(
      `/api/v1/stream/${encodeURIComponent(video.rootId)}`,
      {
        path: video.relativePath,
        sessionToken: sessionResp.sessionToken,
        transcode: "1",
        ...(state.transcodeBaseOffset > 0
          ? { start: state.transcodeBaseOffset }
          : {}),
      }
    );

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

  populateSubtitleTracks(video) {
    const { dom } = this;
    if (!dom.videoElement || !dom.subtitleSelect) {
      return;
    }
    dom.videoElement.querySelectorAll("track").forEach((tr) => tr.remove());
    dom.subtitleSelect.innerHTML = "";

    const offOpt = document.createElement("option");
    offOpt.value = "-1";
    offOpt.textContent = t("Disabled");
    dom.subtitleSelect.appendChild(offOpt);

    const subs = video.subtitles || [];
    subs.forEach((sub, idx) => {
      const track = document.createElement("track");
      track.kind = "subtitles";
      track.label = sub.label || sub.lang || `Subtitle ${idx + 1}`;
      track.srclang = sub.lang || "en";
      track.src = buildAppUrl(
        `/api/v1/subtitles/${encodeURIComponent(video.rootId)}`,
        { path: sub.relativePath }
      );
      dom.videoElement.appendChild(track);

      const opt = document.createElement("option");
      opt.value = String(idx);
      opt.textContent = track.label;
      dom.subtitleSelect.appendChild(opt);
    });
  }

  applySelectedSubtitleTrack(selectedIdx) {
    const tracks = this.dom.videoElement?.textTracks;
    if (!tracks) {
      return;
    }
    for (let i = 0; i < tracks.length; i++) {
      tracks[i].mode = i === selectedIdx ? "showing" : "disabled";
    }
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

  seekRelative(deltaSeconds) {
    const cur = Number(this.dom.videoElement?.currentTime) || 0;
    this.seekToSeconds(Math.max(0, cur + deltaSeconds));
  }

  seekToSeconds(targetSeconds) {
    if (!this.dom.videoElement) {
      return;
    }
    const clamped = Math.max(0, Number(targetSeconds) || 0);
    this.dom.videoElement.currentTime = clamped;
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
    if (!dialog) {
      return;
    }
    if (document.fullscreenElement) {
      document.exitFullscreen?.().catch(() => {});
      dialog.classList.remove("is-fullscreen", "fs-controls-hidden");
    } else if (typeof dialog.requestFullscreen === "function") {
      dialog.requestFullscreen().catch(() => {});
      dialog.classList.add("is-fullscreen");
      this.scheduleFullscreenAutoHide();
    } else {
      dialog.classList.toggle("is-fullscreen");
    }
  }

  handleFullscreenChange() {
    const dialog = this.dom.playerDialog;
    if (!dialog) {
      return;
    }
    if (document.fullscreenElement === dialog) {
      dialog.classList.add("is-fullscreen");
      this.scheduleFullscreenAutoHide();
    } else {
      if (this.fsHideTimer) {
        clearTimeout(this.fsHideTimer);
        this.fsHideTimer = null;
      }
      dialog.classList.remove("is-fullscreen", "fs-controls-hidden");
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
    const { dom, state } = this;
    const cur = Number(dom.videoElement?.currentTime) || 0;
    const dur = state.totalDuration || Number(dom.videoElement?.duration) || 0;

    if (dom.videoElement) {
      dom.videoElement.pause();
      dom.videoElement.removeAttribute("src");
    }
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
