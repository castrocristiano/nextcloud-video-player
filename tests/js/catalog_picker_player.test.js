import { describe, it, expect, vi, beforeEach } from "vitest";
import {
  renderBreadcrumbs,
  renderCatalog,
  renderContinueWatching,
  renderRootsTabs,
} from "../../js/modules/catalog.js";
import {
  addSelectedFoldersToLibrary,
  loadPickerDirectory,
  removeUserRootFromLibrary,
  renderActiveRootsInModal,
  updatePickerSelectionCount,
} from "../../js/modules/picker.js";
import { VideoPlayerController } from "../../js/modules/player.js";
import { PlaybackSessionManager } from "../../js/modules/session.js";
import { createAppState } from "../../js/modules/state.js";

function setupDomFixture() {
  document.body.innerHTML = `
    <section id="rootsBarSection"><div id="rootsTabs"></div></section>
    <section id="continueWatchingBanner" class="hidden">
      <strong id="continueWatchingTitle"></strong>
      <span id="continueWatchingMeta"></span>
      <button id="continueWatchingBtn" type="button"></button>
    </section>
    <ol id="breadcrumbsList"></ol>
    <section id="foldersSection"><span id="foldersCount"></span><div id="foldersGrid"></div></section>
    <section id="videosSection"><span id="videosCount"></span><div id="videosGrid"></div></section>
    <section id="emptyState">
      <h3 id="emptyTitle"></h3>
      <p id="emptyDescription"></p>
      <button id="emptyResetSearchBtn" class="hidden"></button>
    </section>
    <ul id="activeRootsList"></ul>
    <ol id="pickerBreadcrumbsList"></ol>
    <ul id="pickerDirsList"></ul>
    <span id="pickerSelectedCount"></span>
    <button id="addSelectedDirsBtn" disabled></button>
    <div id="playerModal" class="hidden">
      <dialog class="ncvp-player-dialog">
        <h2 id="playerVideoTitle"></h2>
        <span id="playerVideoPath"></span>
        <button id="prevVideoBtn"></button>
        <button id="nextVideoBtn"></button>
        <button id="closePlayerBtn"></button>
        <div id="resumeBanner" class="hidden"><span id="resumeBannerText"></span><button id="restartFromBeginningBtn"></button></div>
        <div id="playerErrorBanner" class="hidden"><button id="errorBackToLibBtn"></button></div>
        <div id="playerLoadingSpinner" class="hidden"></div>
        <div id="subtitleOverlay" class="hidden"></div>
        <video id="videoElement"></video>
        <div id="autoplayOverlay" class="hidden">
          <strong id="autoplayCountdown"></strong>
          <h3 id="autoplayNextTitle"></h3>
          <button id="autoplayPlayNowBtn"></button>
          <button id="autoplayCancelBtn"></button>
        </div>
        <button id="playPauseBtn"></button>
        <button id="seekBackBtn"></button>
        <button id="seekFwdBtn"></button>
        <span id="currentTimeLabel"></span>
        <input type="range" id="seekSlider" />
        <span id="totalDurationLabel"></span>
        <button id="muteBtn"></button>
        <input type="range" id="volumeSlider" />
        <button id="fullscreenBtn"></button>
        <select id="speedSelect"><option value="1">1.0x</option><option value="1.5">1.5x</option></select>
        <select id="subtitleSelect"></select>
        <button id="subSizeMinusBtn"></button>
        <span id="subSizeLabel">100%</span>
        <button id="subSizePlusBtn"></button>
        <select id="audioTrackSelect"></select>
        <input type="checkbox" id="autoplayNextCheckbox" checked />
      </dialog>
    </div>
  `;

  return {
    rootsBarSection: document.getElementById("rootsBarSection"),
    continueWatchingBanner: document.getElementById("continueWatchingBanner"),
    continueWatchingTitle: document.getElementById("continueWatchingTitle"),
    continueWatchingMeta: document.getElementById("continueWatchingMeta"),
    continueWatchingBtn: document.getElementById("continueWatchingBtn"),
    rootsTabs: document.getElementById("rootsTabs"),
    breadcrumbsList: document.getElementById("breadcrumbsList"),
    foldersSection: document.getElementById("foldersSection"),
    foldersCount: document.getElementById("foldersCount"),
    foldersGrid: document.getElementById("foldersGrid"),
    videosSection: document.getElementById("videosSection"),
    videosCount: document.getElementById("videosCount"),
    videosGrid: document.getElementById("videosGrid"),
    emptyState: document.getElementById("emptyState"),
    emptyTitle: document.getElementById("emptyTitle"),
    emptyDescription: document.getElementById("emptyDescription"),
    emptyResetSearchBtn: document.getElementById("emptyResetSearchBtn"),
    activeRootsList: document.getElementById("activeRootsList"),
    pickerBreadcrumbsList: document.getElementById("pickerBreadcrumbsList"),
    pickerDirsList: document.getElementById("pickerDirsList"),
    pickerSelectedCount: document.getElementById("pickerSelectedCount"),
    addSelectedDirsBtn: document.getElementById("addSelectedDirsBtn"),
    playerModal: document.getElementById("playerModal"),
    playerDialog: document.querySelector(".ncvp-player-dialog"),
    playerVideoTitle: document.getElementById("playerVideoTitle"),
    playerVideoPath: document.getElementById("playerVideoPath"),
    prevVideoBtn: document.getElementById("prevVideoBtn"),
    nextVideoBtn: document.getElementById("nextVideoBtn"),
    closePlayerBtn: document.getElementById("closePlayerBtn"),
    resumeBanner: document.getElementById("resumeBanner"),
    resumeBannerText: document.getElementById("resumeBannerText"),
    restartFromBeginningBtn: document.getElementById("restartFromBeginningBtn"),
    playerErrorBanner: document.getElementById("playerErrorBanner"),
    errorBackToLibBtn: document.getElementById("errorBackToLibBtn"),
    playerLoadingSpinner: document.getElementById("playerLoadingSpinner"),
    subtitleOverlay: document.getElementById("subtitleOverlay"),
    videoElement: document.getElementById("videoElement"),
    autoplayOverlay: document.getElementById("autoplayOverlay"),
    autoplayCountdown: document.getElementById("autoplayCountdown"),
    autoplayNextTitle: document.getElementById("autoplayNextTitle"),
    autoplayPlayNowBtn: document.getElementById("autoplayPlayNowBtn"),
    autoplayCancelBtn: document.getElementById("autoplayCancelBtn"),
    playPauseBtn: document.getElementById("playPauseBtn"),
    seekBackBtn: document.getElementById("seekBackBtn"),
    seekFwdBtn: document.getElementById("seekFwdBtn"),
    currentTimeLabel: document.getElementById("currentTimeLabel"),
    seekSlider: document.getElementById("seekSlider"),
    totalDurationLabel: document.getElementById("totalDurationLabel"),
    muteBtn: document.getElementById("muteBtn"),
    volumeSlider: document.getElementById("volumeSlider"),
    fullscreenBtn: document.getElementById("fullscreenBtn"),
    speedSelect: document.getElementById("speedSelect"),
    subtitleSelect: document.getElementById("subtitleSelect"),
    subSizeMinusBtn: document.getElementById("subSizeMinusBtn"),
    subSizeLabel: document.getElementById("subSizeLabel"),
    subSizePlusBtn: document.getElementById("subSizePlusBtn"),
    audioTrackSelect: document.getElementById("audioTrackSelect"),
    autoplayNextCheckbox: document.getElementById("autoplayNextCheckbox"),
  };
}

describe("catalog, picker, and player modules", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    globalThis.fetch = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({
        sessionToken: "tok_play_1",
        roots: [{ id: "u1", label: "Clips", path: "/Clips", scope: "user" }],
        currentPath: "/",
        breadcrumbs: [{ label: "Nextcloud", path: "/" }],
        directories: [{ name: "Clips", path: "/Clips" }],
      }),
    });
  });

  it("renders roots tabs, breadcrumbs, folders, videos, and empty states", () => {
    const dom = setupDomFixture();
    const state = createAppState();

    // Empty library state
    renderCatalog(dom, state, { onOpenFolder: () => {}, onPlayVideo: () => {} });
    expect(dom.emptyTitle.textContent).toBe("No library folders configured");

    // Populate roots, folders, and videos
    state.roots = [
      { id: "g1", label: "Movies", path: "/Movies", scope: "global" },
      { id: "u1", label: "Clips", path: "/Clips", scope: "user" },
    ];
    state.activeRootId = "g1";
    state.breadcrumbs = [
      { label: "Movies", path: "/" },
      { label: "SciFi", path: "/SciFi" },
    ];
    state.folders = [{ name: "Sub", relativePath: "/SciFi/Sub" }];
    state.videos = [
      {
        id: "g1:/SciFi/Ep1.mp4",
        rootId: "g1",
        rootLabel: "Movies",
        name: "Ep1.mp4",
        relativePath: "/SciFi/Ep1.mp4",
        size: 2097152,
        modifiedAt: "2026-11-21T10:00:00Z",
        subtitles: [{ label: "pt-BR (.srt)", lang: "pt-BR", relativePath: "/SciFi/Ep1.pt-BR.srt" }],
        progress: {
          currentTime: 50,
          duration: 200,
          progressRatio: 0.25,
          completed: false,
        },
      },
      {
        id: "g1:/SciFi/Ep2.mp4",
        rootId: "g1",
        rootLabel: "Movies",
        name: "Ep2.mp4",
        relativePath: "/SciFi/Ep2.mp4",
        size: 1048576,
      },
    ];

    let selectedRoot = "";
    renderRootsTabs(dom, state, (id) => {
      selectedRoot = id;
    });
    expect(dom.rootsTabs.children).toHaveLength(2);
    dom.rootsTabs.children[1].click();
    expect(selectedRoot).toBe("u1");

    let navPath = "";
    renderBreadcrumbs(dom, state, (p) => {
      navPath = p;
    });
    dom.breadcrumbsList.querySelector("button").click();
    expect(navPath).toBe("/");

    let openedFolder = "";
    let playedIdx = -1;
    renderCatalog(dom, state, {
      onOpenFolder: (p) => {
        openedFolder = p;
      },
      onPlayVideo: (idx) => {
        playedIdx = idx;
      },
    });

    dom.foldersGrid.querySelector("button").click();
    expect(openedFolder).toBe("/SciFi/Sub");
    dom.videosGrid.querySelector("button").click();
    expect(playedIdx).toBe(0);
  });

  it("handles folder picker modal rendering, selection, addition, and removal", async () => {
    const dom = setupDomFixture();
    const state = createAppState();
    state.roots = [
      { id: "g1", label: "Movies", path: "/Movies", scope: "global" },
      { id: "u1", label: "Clips", path: "/Clips", scope: "user" },
    ];

    let removedId = "";
    renderActiveRootsInModal(dom, state, (id) => {
      removedId = id;
    });
    expect(dom.activeRootsList.children).toHaveLength(2);
    dom.activeRootsList.querySelector("button").click();
    expect(removedId).toBe("u1");

    await loadPickerDirectory(dom, state, "/");
    const checkbox = dom.pickerDirsList.querySelector("input[type=checkbox]");
    checkbox.checked = true;
    checkbox.dispatchEvent(new Event("change"));
    expect(state.pickerSelectedPaths.has("/Clips")).toBe(true);
    updatePickerSelectionCount(dom, state);
    expect(dom.addSelectedDirsBtn.disabled).toBe(false);

    await addSelectedFoldersToLibrary(state);
    expect(state.pickerSelectedPaths.size).toBe(0);

    await removeUserRootFromLibrary(state, "u1");
    expect(state.roots).toHaveLength(1);
  });

  it("drives video player playback, resume, subtitles, audio tracks, shortcuts, and autoplay countdown", async () => {
    const dom = setupDomFixture();
    const state = createAppState();
    state.videos = [
      {
        id: "g1:/Ep1.mp4",
        rootId: "g1",
        rootLabel: "Movies",
        name: "Ep1.mp4",
        relativePath: "/Ep1.mp4",
        subtitles: [{ label: "pt-BR", lang: "pt-BR", relativePath: "/Ep1.pt-BR.srt" }],
        progress: {
          currentTime: 30,
          duration: 120,
          progressRatio: 0.25,
          completed: false,
        },
      },
      {
        id: "g1:/Ep2.mp4",
        rootId: "g1",
        rootLabel: "Movies",
        name: "Ep2.mp4",
        relativePath: "/Ep2.mp4",
        subtitles: [],
      },
    ];

    dom.videoElement.play = vi.fn().mockResolvedValue(undefined);
    dom.videoElement.pause = vi.fn();

    const sessionManager = new PlaybackSessionManager(state);
    const player = new VideoPlayerController(dom, state, sessionManager);
    player.bindEvents();

    await player.openVideoByIndex(0);
    expect(dom.playerVideoTitle.textContent).toBe("Ep1.mp4");
    expect(dom.resumeBanner.classList.contains("hidden")).toBe(false);
    expect(dom.subtitleSelect.options).toHaveLength(2);

    // Restart from beginning
    dom.restartFromBeginningBtn.click();
    expect(dom.videoElement.currentTime).toBe(0);

    // Loaded metadata & audio tracks fallback
    Object.defineProperty(dom.videoElement, "duration", {
      value: 120,
      configurable: true,
    });
    dom.videoElement.dispatchEvent(new Event("loadedmetadata"));
    expect(state.totalDuration).toBe(120);
    expect(dom.audioTrackSelect.disabled).toBe(true);

    // Speed, seek, mute, fullscreen, keyboard shortcuts
    dom.speedSelect.value = "1.5";
    dom.speedSelect.dispatchEvent(new Event("change"));
    expect(dom.videoElement.playbackRate).toBe(1.5);

    document.dispatchEvent(new KeyboardEvent("keydown", { key: "ArrowRight" }));
    expect(dom.videoElement.currentTime).toBe(10);

    document.dispatchEvent(new KeyboardEvent("keydown", { key: "m" }));
    expect(dom.videoElement.muted).toBe(true);

    document.dispatchEvent(new KeyboardEvent("keydown", { key: "f" }));
    expect(dom.playerDialog.classList.contains("is-fullscreen")).toBe(true);

    // Autoplay next countdown on video ended
    vi.useFakeTimers();
    await player.handleVideoEnded();
    expect(dom.autoplayOverlay.classList.contains("hidden")).toBe(false);
    expect(dom.autoplayNextTitle.textContent).toBe("Ep2.mp4");
    vi.advanceTimersByTime(2000);
    expect(dom.autoplayCountdown.textContent).toBe("3");
    dom.autoplayPlayNowBtn.click();
    expect(dom.autoplayOverlay.classList.contains("hidden")).toBe(true);
    vi.useRealTimers();

    // Exercise timeupdate, seekbar input/change, volume slider, play/pause toggle, subtitle & audio track selection, error event, and keyboard keys
    Object.defineProperty(dom.videoElement, "paused", {
      value: false,
      configurable: true,
    });
    dom.playPauseBtn.click();
    expect(dom.videoElement.pause).toHaveBeenCalled();

    dom.seekSlider.value = "45";
    dom.seekSlider.dispatchEvent(new Event("input"));
    expect(dom.currentTimeLabel.textContent).toBe("00:45");
    dom.seekSlider.dispatchEvent(new Event("change"));
    expect(dom.videoElement.currentTime).toBe(45);

    dom.volumeSlider.value = "0.5";
    dom.volumeSlider.dispatchEvent(new Event("input"));
    expect(dom.videoElement.volume).toBe(0.5);

    dom.autoplayNextCheckbox.checked = false;
    dom.autoplayNextCheckbox.dispatchEvent(new Event("change"));
    expect(state.prefs.autoPlayNext).toBe(false);

    Object.defineProperty(dom.videoElement, "textTracks", {
      value: [{ mode: "disabled" }],
      configurable: true,
    });
    dom.subtitleSelect.value = "0";
    dom.subtitleSelect.dispatchEvent(new Event("change"));
    expect(dom.videoElement.textTracks[0].mode).toBe("hidden");

    Object.defineProperty(dom.videoElement, "audioTracks", {
      value: [
        { label: "English", enabled: true },
        { label: "Portuguese", enabled: false },
      ],
      configurable: true,
    });
    player.populateAudioTracks();
    expect(dom.audioTrackSelect.disabled).toBe(false);
    dom.audioTrackSelect.value = "1";
    dom.audioTrackSelect.dispatchEvent(new Event("change"));
    expect(dom.videoElement.audioTracks[1].enabled).toBe(true);

    dom.videoElement.dispatchEvent(new Event("timeupdate"));
    dom.videoElement.dispatchEvent(new Event("error"));
    expect(dom.playerErrorBanner.classList.contains("hidden")).toBe(false);

    document.dispatchEvent(new KeyboardEvent("keydown", { key: "ArrowLeft" }));
    document.dispatchEvent(new KeyboardEvent("keydown", { key: "k" }));
    document.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape" }));
    expect(dom.playerModal.classList.contains("hidden")).toBe(true);
  });

  it("renders continue watching banner, adjusts subtitle font scale with bounds, toggles spinner, and debounces transcoded seek", async () => {
    const dom = setupDomFixture();
    const state = createAppState();
    state.lastWatched = {
      rootId: "u1",
      rootLabel: "Videos",
      relativePath: "/Movies/Wave.avi",
      folderPath: "/Movies",
      name: "Wave.avi",
      currentTime: 300,
      duration: 3600,
    };

    const resumeCb = vi.fn();
    renderContinueWatching(dom, state, resumeCb);
    expect(dom.continueWatchingBanner.classList.contains("hidden")).toBe(false);
    expect(dom.continueWatchingTitle.textContent).toBe("Wave.avi");
    dom.continueWatchingBtn.click();
    expect(resumeCb).toHaveBeenCalledWith(state.lastWatched);

    dom.videoElement.play = vi.fn().mockResolvedValue(undefined);
    dom.videoElement.pause = vi.fn();
    const sessionManager = new PlaybackSessionManager(state);
    const player = new VideoPlayerController(dom, state, sessionManager);
    player.bindEvents();

    // Subtitle font size bounds (75% .. 200%)
    dom.subSizePlusBtn.click();
    expect(dom.subSizeLabel.textContent).toBe("125%");
    expect(dom.subtitleOverlay.style.getPropertyValue("--ncvp-subtitle-scale")).toBe("1.25");
    for (let i = 0; i < 6; i++) dom.subSizePlusBtn.click();
    expect(dom.subSizeLabel.textContent).toBe("200%");
    for (let i = 0; i < 10; i++) dom.subSizeMinusBtn.click();
    expect(dom.subSizeLabel.textContent).toBe("75%");

    // Spinner lifecycle
    dom.videoElement.dispatchEvent(new Event("waiting"));
    expect(dom.playerLoadingSpinner.classList.contains("hidden")).toBe(false);
    dom.videoElement.dispatchEvent(new Event("playing"));
    expect(dom.playerLoadingSpinner.classList.contains("hidden")).toBe(true);

    // Debounced transcoded seek accumulates rapid +10s clicks
    vi.useFakeTimers();
    state.isTranscodedStream = true;
    state.totalDuration = 1200;
    state.currentVideo = { id: "u1:/Wave.avi", rootId: "u1", relativePath: "/Wave.avi", name: "Wave.avi" };
    const switchSpy = vi.spyOn(player, "switchToTranscodedStream").mockResolvedValue(undefined);

    dom.seekFwdBtn.click();
    dom.seekFwdBtn.click();
    dom.seekFwdBtn.click();
    expect(dom.currentTimeLabel.textContent).toBe("00:30");
    expect(switchSpy).not.toHaveBeenCalled();

    await vi.advanceTimersByTimeAsync(350);
    expect(switchSpy).toHaveBeenCalledTimes(1);
    expect(switchSpy).toHaveBeenCalledWith(state.currentVideo, 30);
    vi.useRealTimers();

    // Seeking within already buffered transcoded range adjusts currentTime directly without restarting FFmpeg
    switchSpy.mockClear();
    state.transcodeBaseOffset = 100;
    dom.videoElement.currentTime = 25;
    Object.defineProperty(dom.videoElement, "buffered", {
      value: { length: 1, start: () => 0, end: () => 40 },
      configurable: true,
    });
    dom.seekBackBtn.click();
    expect(dom.videoElement.currentTime).toBe(15);
    expect(switchSpy).not.toHaveBeenCalled();
  });
});
