import { apiGet } from "./modules/api.js";
import {
  renderBreadcrumbs,
  renderCatalog,
  renderRootsTabs,
} from "./modules/catalog.js";
import { t } from "./modules/i18n.js";
import {
  addSelectedFoldersToLibrary,
  loadPickerDirectory,
  removeUserRootFromLibrary,
  renderActiveRootsInModal,
} from "./modules/picker.js";
import { VideoPlayerController } from "./modules/player.js";
import { PlaybackSessionManager } from "./modules/session.js";
import {
  createAppState,
  savePreferencesToServer,
  syncPreferencesFromServer,
} from "./modules/state.js";

function collectDom() {
  return {
    brandHome: document.getElementById("brandHome"),
    searchInput: document.getElementById("searchInput"),
    clearSearchBtn: document.getElementById("clearSearchBtn"),
    sortSelect: document.getElementById("sortSelect"),
    openSessionsModalBtn: document.getElementById("openSessionsModalBtn"),
    openLibraryModalBtn: document.getElementById("openLibraryModalBtn"),
    rootsBarSection: document.getElementById("rootsBarSection"),
    rootsTabs: document.getElementById("rootsTabs"),
    breadcrumbsNav: document.getElementById("breadcrumbsNav"),
    breadcrumbsList: document.getElementById("breadcrumbsList"),
    alertBanner: document.getElementById("alertBanner"),
    alertMessage: document.getElementById("alertMessage"),
    alertRetryBtn: document.getElementById("alertRetryBtn"),
    foldersSection: document.getElementById("foldersSection"),
    foldersCount: document.getElementById("foldersCount"),
    foldersGrid: document.getElementById("foldersGrid"),
    videosSection: document.getElementById("videosSection"),
    videosSectionTitle: document.getElementById("videosSectionTitle"),
    videosCount: document.getElementById("videosCount"),
    videosGrid: document.getElementById("videosGrid"),
    emptyState: document.getElementById("emptyState"),
    emptyTitle: document.getElementById("emptyTitle"),
    emptyDescription: document.getElementById("emptyDescription"),
    emptyAddFoldersBtn: document.getElementById("emptyAddFoldersBtn"),
    emptyResetSearchBtn: document.getElementById("emptyResetSearchBtn"),
    // Player
    playerModal: document.getElementById("playerModal"),
    playerDialog: document.querySelector("#playerModal .ncvp-player-dialog"),
    playerBottomControls: document.getElementById("playerBottomControls"),
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
    videoElement: document.getElementById("videoElement"),
    autoplayOverlay: document.getElementById("autoplayOverlay"),
    autoplayCountdown: document.getElementById("autoplayCountdown"),
    autoplayNextTitle: document.getElementById("autoplayNextTitle"),
    autoplayPlayNowBtn: document.getElementById("autoplayPlayNowBtn"),
    autoplayCancelBtn: document.getElementById("autoplayCancelBtn"),
    speedSelect: document.getElementById("speedSelect"),
    subtitleSelect: document.getElementById("subtitleSelect"),
    audioTrackSelect: document.getElementById("audioTrackSelect"),
    autoplayNextCheckbox: document.getElementById("autoplayNextCheckbox"),
    playPauseBtn: document.getElementById("playPauseBtn"),
    seekBackBtn: document.getElementById("seekBackBtn"),
    seekFwdBtn: document.getElementById("seekFwdBtn"),
    currentTimeLabel: document.getElementById("currentTimeLabel"),
    seekSlider: document.getElementById("seekSlider"),
    totalDurationLabel: document.getElementById("totalDurationLabel"),
    muteBtn: document.getElementById("muteBtn"),
    volumeSlider: document.getElementById("volumeSlider"),
    fullscreenBtn: document.getElementById("fullscreenBtn"),
    // Library Modal
    libraryModal: document.getElementById("libraryModal"),
    closeLibraryModalBtn: document.getElementById("closeLibraryModalBtn"),
    activeRootsList: document.getElementById("activeRootsList"),
    selectCurrentDirBtn: document.getElementById("selectCurrentDirBtn"),
    pickerBreadcrumbsList: document.getElementById("pickerBreadcrumbsList"),
    pickerDirsList: document.getElementById("pickerDirsList"),
    pickerSelectedCount: document.getElementById("pickerSelectedCount"),
    addSelectedDirsBtn: document.getElementById("addSelectedDirsBtn"),
    // Sessions Modal
    sessionsModal: document.getElementById("sessionsModal"),
    closeSessionsModalBtn: document.getElementById("closeSessionsModalBtn"),
    activeSessionsList: document.getElementById("activeSessionsList"),
  };
}

export async function bootApp() {
  const rootEl = document.getElementById("ncvp-app");
  if (!rootEl) {
    return;
  }

  const dom = collectDom();
  const state = createAppState();
  const sessionManager = new PlaybackSessionManager(state);

  const refreshView = () => {
    renderRootsTabs(dom, state, (rootId) => {
      state.activeRootId = rootId;
      state.currentPath = "/";
      state.isSearching = false;
      loadCurrentFolder();
    });
    renderBreadcrumbs(dom, state, (targetPath) => {
      state.currentPath = targetPath;
      loadCurrentFolder();
    });
    renderCatalog(dom, state, {
      onOpenFolder: (relPath) => {
        state.currentPath = relPath;
        loadCurrentFolder();
      },
      onPlayVideo: (idx) => {
        player.openVideoByIndex(idx).catch((err) => showAlert(err.message));
      },
    });
  };

  const player = new VideoPlayerController(dom, state, sessionManager, {
    onCatalogRefresh: refreshView,
  });
  player.bindEvents();

  function showAlert(msg) {
    if (!dom.alertBanner || !dom.alertMessage) {
      return;
    }
    if (!msg) {
      dom.alertBanner.classList.add("hidden");
      return;
    }
    dom.alertMessage.textContent = msg;
    dom.alertBanner.classList.remove("hidden");
  }

  async function loadCurrentFolder() {
    showAlert("");
    if (!state.activeRootId) {
      state.folders = [];
      state.videos = [];
      refreshView();
      return;
    }
    try {
      const data = await apiGet("/api/v1/catalog", {
        rootId: state.activeRootId,
        path: state.currentPath,
        sort: state.prefs.sortBy,
      });
      state.breadcrumbs = data.breadcrumbs || [];
      state.folders = data.folders || [];
      state.videos = data.videos || [];
      refreshView();
    } catch (err) {
      showAlert(err.message);
    }
  }

  async function loadRoots() {
    const data = await apiGet("/api/v1/roots");
    state.roots = data.roots || [];
    state.userId = data.meta?.userId || "";
    state.capabilities = data.capabilities || { ffmpegEnabled: false };
    if (
      state.roots.length > 0 &&
      !state.roots.some((r) => r.id === state.activeRootId)
    ) {
      state.activeRootId = state.roots[0].id;
      state.currentPath = "/";
    } else if (state.roots.length === 0) {
      state.activeRootId = "";
    }
    await loadCurrentFolder();
  }

  await syncPreferencesFromServer(state);
  if (dom.sortSelect) {
    dom.sortSelect.value = state.prefs.sortBy;
  }
  await loadRoots();

  dom.sortSelect?.addEventListener("change", async () => {
    await savePreferencesToServer(state, { sortBy: dom.sortSelect.value });
    await loadCurrentFolder();
  });

  dom.openLibraryModalBtn?.addEventListener("click", async () => {
    dom.libraryModal?.classList.remove("hidden");
    renderActiveRootsInModal(dom, state, async (rootId) => {
      await removeUserRootFromLibrary(state, rootId);
      renderActiveRootsInModal(dom, state, () => {});
      await loadRoots();
    });
    await loadPickerDirectory(dom, state, "/");
  });

  dom.closeLibraryModalBtn?.addEventListener("click", () => {
    dom.libraryModal?.classList.add("hidden");
  });

  dom.addSelectedDirsBtn?.addEventListener("click", async () => {
    await addSelectedFoldersToLibrary(state);
    dom.libraryModal?.classList.add("hidden");
    await loadRoots();
  });

  dom.openSessionsModalBtn?.addEventListener("click", async () => {
    dom.sessionsModal?.classList.remove("hidden");
    const data = await sessionManager.fetchActiveSessions();
    if (dom.activeSessionsList) {
      dom.activeSessionsList.innerHTML = "";
      const list = data.sessions || [];
      if (list.length === 0) {
        const li = document.createElement("li");
        li.className = "ncvp-root-item";
        li.textContent = t("No active playback sessions.");
        dom.activeSessionsList.appendChild(li);
      } else {
        for (const s of list) {
          const li = document.createElement("li");
          li.className = "ncvp-root-item";
          const span = document.createElement("span");
          span.textContent = `${s.relativePath} (${s.status})`;
          const btn = document.createElement("button");
          btn.type = "button";
          btn.className = "ncvp-btn ncvp-btn-danger ncvp-btn-sm";
          btn.textContent = t("Terminate");
          btn.addEventListener("click", async () => {
            await sessionManager.revokeSession(s.sessionToken);
            li.remove();
          });
          li.appendChild(span);
          li.appendChild(btn);
          dom.activeSessionsList.appendChild(li);
        }
      }
    }
  });

  dom.closeSessionsModalBtn?.addEventListener("click", () => {
    dom.sessionsModal?.classList.add("hidden");
  });

  window.addEventListener("pagehide", () => {
    sessionManager.closeCurrentSession({ useBeacon: true });
  });
}

if (typeof document !== "undefined") {
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
      bootApp().catch(() => {});
    });
  } else {
    bootApp().catch(() => {});
  }
}
