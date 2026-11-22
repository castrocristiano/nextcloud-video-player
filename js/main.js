(function () {
  "use strict";

  // --- js/modules/i18n.js ---
const APP_ID = "nextcloud_video_player";

function interpolate(template, vars = {}) {
  if (!vars || typeof vars !== "object") {
    return template;
  }
  return String(template).replace(/\{(\w+)\}/g, (match, key) => {
    return Object.prototype.hasOwnProperty.call(vars, key)
      ? String(vars[key])
      : match;
  });
}

function translate(text, vars = {}) {
  if (typeof window !== "undefined") {
    if (typeof window.t === "function") {
      const translated = window.t(APP_ID, text, vars);
      if (typeof translated === "string" && translated.length > 0) {
        return interpolate(translated, vars);
      }
    }
    if (
      typeof window.OC !== "undefined" &&
      window.OC?.L10N &&
      typeof window.OC.L10N.translate === "function"
    ) {
      const translated = window.OC.L10N.translate(APP_ID, text, vars);
      if (typeof translated === "string" && translated.length > 0) {
        return interpolate(translated, vars);
      }
    }
  }
  return interpolate(text, vars);
}

function translatePlural(singular, plural, count, vars = {}) {
  const safeCount = Number.isFinite(Number(count)) ? Number(count) : 0;
  const mergedVars = { count: safeCount, ...vars };

  if (typeof window !== "undefined") {
    if (typeof window.n === "function") {
      const translated = window.n(APP_ID, singular, plural, safeCount, mergedVars);
      if (typeof translated === "string" && translated.length > 0) {
        return interpolate(translated, mergedVars);
      }
    }
    if (
      typeof window.OC !== "undefined" &&
      window.OC?.L10N &&
      typeof window.OC.L10N.translatePlural === "function"
    ) {
      const translated = window.OC.L10N.translatePlural(
        APP_ID,
        singular,
        plural,
        safeCount,
        mergedVars
      );
      if (typeof translated === "string" && translated.length > 0) {
        return interpolate(translated, mergedVars);
      }
    }
  }

  const chosen = safeCount === 1 ? singular : plural;
  return interpolate(chosen.replace(/%n/g, String(safeCount)), mergedVars);
}

function getLocale() {
  if (typeof window !== "undefined") {
    if (
      typeof window.OC !== "undefined" &&
      typeof window.OC.getLocale === "function"
    ) {
      const loc = window.OC.getLocale();
      if (loc && typeof loc === "string") {
        return loc.replace(/_/g, "-");
      }
    }
    if (typeof document !== "undefined" && document.documentElement?.lang) {
      return document.documentElement.lang.replace(/_/g, "-");
    }
  }
  return "en";
}

const t = translate;
const n = translatePlural;

  // --- js/modules/api.js ---
function buildAppUrl(path, queryParams = {}) {
  const cleanPath = path.startsWith("/") ? path : `/${path}`;
  let baseUrl = `/apps/${APP_ID}${cleanPath}`;

  if (
    typeof window !== "undefined" &&
    window.OC &&
    typeof window.OC.generateUrl === "function"
  ) {
    baseUrl = window.OC.generateUrl(`/apps/${APP_ID}${cleanPath}`);
  }

  const entries = Object.entries(queryParams).filter(
    ([, val]) => val !== undefined && val !== null && val !== ""
  );
  if (entries.length === 0) {
    return baseUrl;
  }

  const search = new URLSearchParams();
  for (const [key, val] of entries) {
    search.set(key, String(val));
  }
  const sep = baseUrl.includes("?") ? "&" : "?";
  return `${baseUrl}${sep}${search.toString()}`;
}

function getRequestToken() {
  if (typeof window !== "undefined" && window.OC?.requestToken) {
    return String(window.OC.requestToken);
  }
  if (typeof document !== "undefined" && document.head?.dataset?.requesttoken) {
    return String(document.head.dataset.requesttoken);
  }
  return "";
}

async function requestJson(method, path, { query = {}, body = null, keepalive = false } = {}) {
  const url = buildAppUrl(path, query);
  const headers = {
    Accept: "application/json",
  };

  const token = getRequestToken();
  if (token) {
    headers.requesttoken = token;
  }

  const options = {
    method,
    headers,
    credentials: "same-origin",
    keepalive: Boolean(keepalive),
  };

  if (body !== null && method !== "GET") {
    headers["Content-Type"] = "application/json";
    options.body = JSON.stringify(body);
  }

  const response = await fetch(url, options);
  let payload = null;
  try {
    payload = await response.json();
  } catch {
    payload = null;
  }

  if (!response.ok) {
    const errMessage =
      payload?.error?.message || `HTTP Error ${response.status}`;
    const err = new Error(errMessage);
    err.status = response.status;
    err.payload = payload?.error || payload;
    throw err;
  }

  return payload;
}

function apiGet(path, query = {}) {
  return requestJson("GET", path, { query });
}

function apiPost(path, body = {}, options = {}) {
  return requestJson("POST", path, { body, ...options });
}

function apiPut(path, body = {}, options = {}) {
  return requestJson("PUT", path, { body, ...options });
}

function apiDelete(path, query = {}) {
  return requestJson("DELETE", path, { query });
}

function sendSessionCloseBeacon(sessionToken, payload = {}) {
  if (!sessionToken) {
    return false;
  }
  const token = getRequestToken();
  const url = buildAppUrl(`/api/v1/sessions/${encodeURIComponent(sessionToken)}/close`, {
    requesttoken: token,
  });

  if (typeof fetch === "function") {
    fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        ...(token ? { requesttoken: token } : {}),
      },
      body: JSON.stringify(payload),
      keepalive: true,
      credentials: "same-origin",
    }).catch(() => {});
    return true;
  }
  return false;
}

  // --- js/modules/formatters.js ---
const COMPLETION_THRESHOLD = 0.95;

function formatBytes(bytes) {
  const num = Number(bytes);
  if (!Number.isFinite(num) || num <= 0) {
    return "0 B";
  }
  const units = ["B", "KB", "MB", "GB", "TB"];
  const idx = Math.min(
    units.length - 1,
    Math.floor(Math.log(num) / Math.log(1024))
  );
  const val = num / Math.pow(1024, idx);
  return `${idx === 0 ? val.toFixed(0) : val.toFixed(1)} ${units[idx]}`;
}

function formatDate(isoStr, localeOverride = null) {
  if (!isoStr) {
    return "";
  }
  try {
    const date = new Date(isoStr);
    if (Number.isNaN(date.getTime())) {
      return "";
    }
    const locale = localeOverride || getLocale() || "en";
    return new Intl.DateTimeFormat(locale, {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
    }).format(date);
  } catch {
    return "";
  }
}

function formatDuration(seconds) {
  const sec = Number(seconds);
  if (!Number.isFinite(sec) || sec < 0) {
    return "00:00";
  }
  const total = Math.floor(sec);
  const hours = Math.floor(total / 3600);
  const minutes = Math.floor((total % 3600) / 60);
  const remSeconds = total % 60;
  const mm = String(minutes).padStart(2, "0");
  const ss = String(remSeconds).padStart(2, "0");
  return hours > 0 ? `${hours}:${mm}:${ss}` : `${mm}:${ss}`;
}

function computeProgressEntry(videoId, currentTime, duration, forceCompleted = false) {
  const dur = Number(duration);
  const cur = Math.max(0, Number(currentTime) || 0);
  if (!videoId || !Number.isFinite(dur) || dur <= 0) {
    return null;
  }
  const ratio = Math.min(1, Math.max(0, cur / dur));
  const completed = Boolean(forceCompleted || ratio >= COMPLETION_THRESHOLD);
  return {
    videoId,
    currentTime: completed ? 0 : cur,
    duration: dur,
    progressRatio: completed ? 1.0 : ratio,
    completed,
    updatedAt: Date.now(),
  };
}

  // --- js/modules/state.js ---
const PREFS_CACHE_KEY = "ncvp:prefs";
const PROGRESS_CACHE_PREFIX = "ncvp:progress:";

function getDefaultPreferences() {
  return {
    playbackRate: 1.0,
    autoPlayNext: true,
    sortBy: "name_asc",
    volume: 1.0,
    muted: false,
    preferredSubtitle: null,
  };
}

function createAppState() {
  return {
    userId: "",
    roots: [],
    capabilities: { ffmpegEnabled: false },
    activeRootId: "",
    currentPath: "/",
    breadcrumbs: [],
    folders: [],
    videos: [],
    searchQuery: "",
    isSearching: false,
    prefs: loadCachedPreferences(),
    progressByVideoId: new Map(),
    // Player state
    currentVideoIndex: -1,
    currentVideo: null,
    activeSessionToken: "",
    triedTranscodeFallback: false,
    isTranscodedStream: false,
    transcodeBaseOffset: 0,
    totalDuration: 0,
    isDraggingSeek: false,
    // Folder Picker state
    pickerCurrentPath: "/",
    pickerSelectedPaths: new Set(),
  };
}

function loadCachedPreferences() {
  const defaults = getDefaultPreferences();
  try {
    const raw = localStorage.getItem(PREFS_CACHE_KEY);
    if (!raw) {
      return defaults;
    }
    const parsed = JSON.parse(raw);
    return {
      playbackRate: Number(parsed.playbackRate) || 1.0,
      autoPlayNext:
        typeof parsed.autoPlayNext === "boolean" ? parsed.autoPlayNext : true,
      sortBy: parsed.sortBy || "name_asc",
      volume:
        typeof parsed.volume === "number"
          ? Math.min(1, Math.max(0, parsed.volume))
          : 1.0,
      muted: Boolean(parsed.muted),
      preferredSubtitle: parsed.preferredSubtitle || null,
    };
  } catch {
    return defaults;
  }
}

function cachePreferencesLocally(prefs) {
  try {
    localStorage.setItem(PREFS_CACHE_KEY, JSON.stringify(prefs));
  } catch {
    // Ignore storage quota errors
  }
}

async function syncPreferencesFromServer(state) {
  try {
    const data = await apiGet("/api/v1/preferences");
    if (data?.preferences) {
      state.prefs = {
        ...getDefaultPreferences(),
        ...data.preferences,
      };
      cachePreferencesLocally(state.prefs);
    }
  } catch {
    // Keep cached preferences on offline/error
  }
  return state.prefs;
}

async function savePreferencesToServer(state, partialPrefs = {}) {
  state.prefs = {
    ...state.prefs,
    ...partialPrefs,
  };
  cachePreferencesLocally(state.prefs);

  try {
    const data = await apiPut("/api/v1/preferences", state.prefs);
    if (data?.preferences) {
      state.prefs = {
        ...state.prefs,
        ...data.preferences,
      };
      cachePreferencesLocally(state.prefs);
    }
  } catch {
    // Keep optimistic update
  }
  return state.prefs;
}

function getVideoProgress(state, video) {
  if (!video) {
    return null;
  }
  const videoId = video.id || `${video.rootId}:${video.relativePath}`;
  if (video.progress && typeof video.progress === "object") {
    state.progressByVideoId.set(videoId, video.progress);
    return video.progress;
  }
  if (state.progressByVideoId.has(videoId)) {
    return state.progressByVideoId.get(videoId);
  }
  try {
    const raw = localStorage.getItem(
      `${PROGRESS_CACHE_PREFIX}${state.userId || "anon"}:${videoId}`
    );
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

function recordLocalVideoProgress(
  state,
  video,
  currentTime,
  duration,
  forceCompleted = false
) {
  if (!video) {
    return null;
  }
  const videoId = video.id || `${video.rootId}:${video.relativePath}`;
  const entry = computeProgressEntry(
    videoId,
    currentTime,
    duration,
    forceCompleted
  );
  if (!entry) {
    return null;
  }
  video.progress = entry;
  state.progressByVideoId.set(videoId, entry);
  try {
    localStorage.setItem(
      `${PROGRESS_CACHE_PREFIX}${state.userId || "anon"}:${videoId}`,
      JSON.stringify(entry)
    );
  } catch {
    // Ignore localStorage quota failures
  }
  return entry;
}

  // --- js/modules/session.js ---
const HEARTBEAT_INTERVAL_MS = 15000;

class PlaybackSessionManager {
  constructor(state, { onSessionError = null } = {}) {
    this.state = state;
    this.onSessionError = onSessionError;
    this.heartbeatTimer = null;
  }

  async startSession(video, streamMode = "direct") {
    await this.closeCurrentSession();

    const response = await apiPost("/api/v1/sessions", {
      rootId: video.rootId,
      path: video.relativePath,
      streamMode,
    });

    this.state.activeSessionToken = response.sessionToken || "";
    if (response.progress) {
      video.progress = response.progress;
      this.state.progressByVideoId.set(video.id, response.progress);
    }

    return response;
  }

  startHeartbeatLoop(getPlaybackSnapshot) {
    this.stopHeartbeatLoop();
    this.heartbeatTimer = setInterval(() => {
      const snapshot = getPlaybackSnapshot();
      if (snapshot) {
        this.sendHeartbeat(snapshot).catch((err) => {
          if (typeof this.onSessionError === "function") {
            this.onSessionError(err);
          }
        });
      }
    }, HEARTBEAT_INTERVAL_MS);
  }

  stopHeartbeatLoop() {
    if (this.heartbeatTimer) {
      clearInterval(this.heartbeatTimer);
      this.heartbeatTimer = null;
    }
  }

  async sendHeartbeat({
    currentTime = 0,
    duration = 0,
    status = "active",
    completed = false,
  } = {}) {
    const token = this.state.activeSessionToken;
    if (!token) {
      return null;
    }

    if (this.state.currentVideo && duration > 0) {
      recordLocalVideoProgress(
        this.state,
        this.state.currentVideo,
        currentTime,
        duration,
        completed
      );
    }

    const res = await apiPut(`/api/v1/sessions/${encodeURIComponent(token)}/heartbeat`, {
      currentTime,
      duration,
      status,
      completed,
    });

    if (res?.progress && this.state.currentVideo) {
      this.state.currentVideo.progress = res.progress;
      this.state.progressByVideoId.set(this.state.currentVideo.id, res.progress);
    }

    return res;
  }

  async closeCurrentSession({
    currentTime = null,
    duration = null,
    completed = false,
    useBeacon = false,
  } = {}) {
    this.stopHeartbeatLoop();
    const token = this.state.activeSessionToken;
    if (!token) {
      return null;
    }
    this.state.activeSessionToken = "";

    const payload = {};
    if (Number.isFinite(currentTime) && Number.isFinite(duration) && duration > 0) {
      payload.currentTime = currentTime;
      payload.duration = duration;
      payload.completed = Boolean(completed);
      if (this.state.currentVideo) {
        recordLocalVideoProgress(
          this.state,
          this.state.currentVideo,
          currentTime,
          duration,
          completed
        );
      }
    }

    if (useBeacon) {
      sendSessionCloseBeacon(token, payload);
      return null;
    }

    try {
      return await apiPost(
        `/api/v1/sessions/${encodeURIComponent(token)}/close`,
        payload
      );
    } catch {
      return null;
    }
  }

  async fetchActiveSessions() {
    return apiGet("/api/v1/sessions");
  }

  async revokeSession(sessionToken) {
    if (sessionToken === this.state.activeSessionToken) {
      this.stopHeartbeatLoop();
      this.state.activeSessionToken = "";
    }
    return apiDelete(`/api/v1/sessions/${encodeURIComponent(sessionToken)}`);
  }
}

  // --- js/modules/catalog.js ---
function renderRootsTabs(dom, state, onSelectRoot) {
  if (!dom.rootsTabs) {
    return;
  }
  dom.rootsTabs.innerHTML = "";

  if (!state.roots || state.roots.length === 0) {
    dom.rootsBarSection?.classList.add("hidden");
    return;
  }

  dom.rootsBarSection?.classList.remove("hidden");
  for (const root of state.roots) {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className =
      "ncvp-root-tab" + (root.id === state.activeRootId ? " active" : "");
    const scopeLabel =
      root.scope === "global" ? ` (${t("Global")})` : "";
    btn.textContent = `${root.label}${scopeLabel}`;
    btn.addEventListener("click", () => onSelectRoot(root.id));
    dom.rootsTabs.appendChild(btn);
  }
}

function renderBreadcrumbs(dom, state, onNavigatePath) {
  if (!dom.breadcrumbsList) {
    return;
  }
  dom.breadcrumbsList.innerHTML = "";

  if (state.isSearching) {
    const li = document.createElement("li");
    li.textContent = t("Search results for \"{query}\"", {
      query: state.searchQuery,
    });
    dom.breadcrumbsList.appendChild(li);
    return;
  }

  const crumbs = state.breadcrumbs || [];
  crumbs.forEach((crumb, idx) => {
    const li = document.createElement("li");
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "ncvp-breadcrumb-btn";
    btn.textContent = crumb.label;
    btn.addEventListener("click", () => onNavigatePath(crumb.path));
    li.appendChild(btn);

    if (idx < crumbs.length - 1) {
      const sep = document.createElement("span");
      sep.textContent = " / ";
      li.appendChild(sep);
    }
    dom.breadcrumbsList.appendChild(li);
  });
}

function renderCatalog(dom, state, { onOpenFolder, onPlayVideo }) {
  const folders = state.folders || [];
  const videos = state.videos || [];

  // Folders section
  if (dom.foldersSection && dom.foldersGrid && dom.foldersCount) {
    dom.foldersGrid.innerHTML = "";
    dom.foldersCount.textContent = String(folders.length);
    if (folders.length === 0 || state.isSearching) {
      dom.foldersSection.classList.add("hidden");
    } else {
      dom.foldersSection.classList.remove("hidden");
      for (const folder of folders) {
        const card = document.createElement("button");
        card.type = "button";
        card.className = "ncvp-folder-card";
        const title = document.createElement("strong");
        title.textContent = folder.name;
        card.appendChild(title);
        card.addEventListener("click", () => onOpenFolder(folder.relativePath));
        dom.foldersGrid.appendChild(card);
      }
    }
  }

  // Videos section
  if (dom.videosSection && dom.videosGrid && dom.videosCount) {
    dom.videosGrid.innerHTML = "";
    dom.videosCount.textContent = String(videos.length);
    if (videos.length === 0) {
      dom.videosSection.classList.add("hidden");
    } else {
      dom.videosSection.classList.remove("hidden");
      videos.forEach((video, index) => {
        const card = document.createElement("button");
        card.type = "button";
        card.className = "ncvp-video-card";

        const thumbWrap = document.createElement("div");
        thumbWrap.className = "ncvp-video-thumb-wrap";

        const img = document.createElement("img");
        img.className = "ncvp-video-thumb";
        img.loading = "lazy";
        img.alt = video.title || video.name;
        img.src = buildAppUrl(`/api/v1/thumb/${encodeURIComponent(video.rootId)}`, {
          path: video.relativePath,
        });
        thumbWrap.appendChild(img);

        const prog = getVideoProgress(state, video);
        if (prog && !prog.completed && prog.progressRatio > 0) {
          const barWrap = document.createElement("div");
          barWrap.className = "ncvp-progress-bar-wrap";
          const fill = document.createElement("div");
          fill.className = "ncvp-progress-bar-fill";
          fill.style.width = `${Math.round(prog.progressRatio * 100)}%`;
          barWrap.appendChild(fill);
          thumbWrap.appendChild(barWrap);
        }

        const body = document.createElement("div");
        body.className = "ncvp-video-body";

        const h3 = document.createElement("h3");
        h3.className = "ncvp-video-title";
        h3.textContent = video.name;
        body.appendChild(h3);

        const meta = document.createElement("div");
        meta.className = "ncvp-video-meta";
        const sizeSpan = document.createElement("span");
        sizeSpan.textContent = formatBytes(video.size);
        meta.appendChild(sizeSpan);

        const dateStr = formatDate(video.modifiedAt);
        if (dateStr) {
          const dateSpan = document.createElement("span");
          dateSpan.textContent = dateStr;
          meta.appendChild(dateSpan);
        }

        if (state.isSearching && video.rootLabel) {
          const locSpan = document.createElement("span");
          locSpan.textContent = `${video.rootLabel}:${video.folderPath || "/"}`;
          meta.appendChild(locSpan);
        }

        body.appendChild(meta);
        card.appendChild(thumbWrap);
        card.appendChild(body);

        card.addEventListener("click", () => onPlayVideo(index));
        dom.videosGrid.appendChild(card);
      });
    }
  }

  // Empty state
  if (dom.emptyState) {
    const isEmpty =
      (state.isSearching && videos.length === 0) ||
      (!state.isSearching && folders.length === 0 && videos.length === 0);

    if (!isEmpty) {
      dom.emptyState.classList.add("hidden");
    } else {
      dom.emptyState.classList.remove("hidden");
      if (state.roots.length === 0) {
        dom.emptyTitle.textContent = t("No library folders configured");
        dom.emptyDescription.textContent = t(
          "Select one or more Nextcloud folders to start browsing and watching your videos."
        );
        dom.emptyResetSearchBtn?.classList.add("hidden");
      } else if (state.isSearching) {
        dom.emptyTitle.textContent = t("No matching videos found");
        dom.emptyDescription.textContent = t(
          "No videos matched your search term across the configured folders."
        );
        dom.emptyResetSearchBtn?.classList.remove("hidden");
      } else {
        dom.emptyTitle.textContent = t("This folder has no videos");
        dom.emptyDescription.textContent = t(
          "Choose another folder or add more folders to your library."
        );
        dom.emptyResetSearchBtn?.classList.add("hidden");
      }
    }
  }
}

  // --- js/modules/picker.js ---
function renderActiveRootsInModal(dom, state, onRemoveRoot) {
  if (!dom.activeRootsList) {
    return;
  }
  dom.activeRootsList.innerHTML = "";

  if (!state.roots || state.roots.length === 0) {
    const li = document.createElement("li");
    li.className = "ncvp-root-item";
    li.textContent = t("No folders configured yet.");
    dom.activeRootsList.appendChild(li);
    return;
  }

  for (const root of state.roots) {
    const li = document.createElement("li");
    li.className = "ncvp-root-item";

    const info = document.createElement("div");
    const strong = document.createElement("strong");
    strong.textContent = root.label;
    const sub = document.createElement("span");
    sub.textContent = ` (${root.path})`;
    info.appendChild(strong);
    info.appendChild(sub);
    li.appendChild(info);

    if (root.scope === "global") {
      const badge = document.createElement("span");
      badge.className = "ncvp-badge";
      badge.textContent = t("Pre-configured by Admin");
      li.appendChild(badge);
    } else {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "ncvp-btn ncvp-btn-danger ncvp-btn-sm";
      btn.textContent = t("Remove");
      btn.addEventListener("click", () => onRemoveRoot(root.id));
      li.appendChild(btn);
    }

    dom.activeRootsList.appendChild(li);
  }
}

function updatePickerSelectionCount(dom, state) {
  const count = state.pickerSelectedPaths.size;
  if (dom.pickerSelectedCount) {
    dom.pickerSelectedCount.textContent = n(
      "%n folder selected",
      "%n folders selected",
      count
    );
  }
  if (dom.addSelectedDirsBtn) {
    dom.addSelectedDirsBtn.disabled = count === 0;
  }
}

async function loadPickerDirectory(dom, state, path = "/") {
  const listing = await apiGet("/api/v1/picker", { path });
  state.pickerCurrentPath = listing.currentPath || "/";

  if (dom.pickerBreadcrumbsList) {
    dom.pickerBreadcrumbsList.innerHTML = "";
    for (const crumb of listing.breadcrumbs || []) {
      const li = document.createElement("li");
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "ncvp-breadcrumb-btn";
      btn.textContent = crumb.label;
      btn.addEventListener("click", () => loadPickerDirectory(dom, state, crumb.path));
      li.appendChild(btn);
      dom.pickerBreadcrumbsList.appendChild(li);
    }
  }

  if (dom.pickerDirsList) {
    dom.pickerDirsList.innerHTML = "";
    const dirs = listing.directories || [];
    if (dirs.length === 0) {
      const emptyLi = document.createElement("li");
      emptyLi.className = "ncvp-picker-dir-item";
      emptyLi.textContent = t("No subfolders inside this directory.");
      dom.pickerDirsList.appendChild(emptyLi);
    } else {
      for (const dir of dirs) {
        const li = document.createElement("li");
        li.className = "ncvp-picker-dir-item";

        const label = document.createElement("label");
        label.className = "ncvp-checkbox-label";
        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.checked = state.pickerSelectedPaths.has(dir.path);
        checkbox.addEventListener("change", () => {
          if (checkbox.checked) {
            state.pickerSelectedPaths.add(dir.path);
          } else {
            state.pickerSelectedPaths.delete(dir.path);
          }
          updatePickerSelectionCount(dom, state);
        });

        const nameSpan = document.createElement("span");
        nameSpan.textContent = ` ${dir.name}`;
        label.appendChild(checkbox);
        label.appendChild(nameSpan);

        const openBtn = document.createElement("button");
        openBtn.type = "button";
        openBtn.className = "ncvp-btn ncvp-btn-secondary ncvp-btn-sm";
        openBtn.textContent = t("Open");
        openBtn.addEventListener("click", () =>
          loadPickerDirectory(dom, state, dir.path)
        );

        li.appendChild(label);
        li.appendChild(openBtn);
        dom.pickerDirsList.appendChild(li);
      }
    }
  }

  updatePickerSelectionCount(dom, state);
}

async function addSelectedFoldersToLibrary(state) {
  const paths = Array.from(state.pickerSelectedPaths);
  if (paths.length === 0) {
    return state.roots;
  }
  const res = await apiPost("/api/v1/roots", { paths });
  state.pickerSelectedPaths.clear();
  state.roots = res.roots || [];
  return state.roots;
}

async function removeUserRootFromLibrary(state, rootId) {
  const res = await apiDelete(`/api/v1/roots/${encodeURIComponent(rootId)}`);
  state.roots = res.roots || [];
  return state.roots;
}

  // --- js/modules/player.js ---
const FULLSCREEN_HIDE_DELAY_MS = 2500;
const AUTOPLAY_COUNTDOWN_SECONDS = 5;

class VideoPlayerController {
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
    const sessionResp = await this.sessionManager.startSession(video, "direct");
    const sessionToken = sessionResp.sessionToken;

    const streamUrl = buildAppUrl(
      `/api/v1/stream/${encodeURIComponent(video.rootId)}`,
      {
        path: video.relativePath,
        sessionToken,
      }
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
      savedProgress.currentTime > 3
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

  // --- main bootstrap ---
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
    pickerStatusMsg: document.getElementById("pickerStatusMsg"),
    pickerDirsList: document.getElementById("pickerDirsList"),
    pickerSelectedCount: document.getElementById("pickerSelectedCount"),
    addSelectedDirsBtn: document.getElementById("addSelectedDirsBtn"),
    // Sessions Modal
    sessionsModal: document.getElementById("sessionsModal"),
    closeSessionsModalBtn: document.getElementById("closeSessionsModalBtn"),
    sessionsStatusMsg: document.getElementById("sessionsStatusMsg"),
    activeSessionsList: document.getElementById("activeSessionsList"),
  };
}

async function bootApp() {
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

  function showPickerStatus(msg) {
    if (!dom.pickerStatusMsg) {
      return;
    }
    if (!msg) {
      dom.pickerStatusMsg.textContent = "";
      dom.pickerStatusMsg.classList.add("hidden");
      return;
    }
    dom.pickerStatusMsg.textContent = msg;
    dom.pickerStatusMsg.classList.remove("hidden");
  }

  async function loadCurrentFolder() {
    showAlert("");
    if (state.isSearching && state.searchQuery.trim().length > 0) {
      await runSearch(state.searchQuery.trim());
      return;
    }
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
      refreshView();
    }
  }

  async function runSearch(query) {
    const trimmed = String(query || "").trim();
    if (!trimmed) {
      state.isSearching = false;
      state.searchQuery = "";
      dom.clearSearchBtn?.classList.add("hidden");
      await loadCurrentFolder();
      return;
    }

    state.isSearching = true;
    state.searchQuery = trimmed;
    dom.clearSearchBtn?.classList.remove("hidden");
    showAlert("");

    try {
      const data = await apiGet("/api/v1/search", {
        q: trimmed,
        sort: state.prefs.sortBy,
      });
      state.folders = [];
      state.videos = data.videos || [];
      refreshView();
    } catch (err) {
      showAlert(err.message);
      refreshView();
    }
  }

  async function loadRoots() {
    try {
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
    } catch (err) {
      showAlert(err.message);
      refreshView();
    }
  }

  async function handleRemoveRoot(rootId) {
    try {
      showPickerStatus("");
      await removeUserRootFromLibrary(state, rootId);
      renderActiveRootsInModal(dom, state, handleRemoveRoot);
      await loadRoots();
    } catch (err) {
      showPickerStatus(err.message);
    }
  }

  async function openLibraryModal() {
    dom.libraryModal?.classList.remove("hidden");
    showPickerStatus("");
    renderActiveRootsInModal(dom, state, handleRemoveRoot);
    try {
      await loadPickerDirectory(dom, state, state.pickerCurrentPath || "/");
    } catch (err) {
      showPickerStatus(err.message);
    }
  }

  // Bind all topbar, catalog, picker, and session modal events IMMEDIATELY
  dom.openLibraryModalBtn?.addEventListener("click", () => {
    openLibraryModal();
  });

  dom.emptyAddFoldersBtn?.addEventListener("click", () => {
    openLibraryModal();
  });

  dom.closeLibraryModalBtn?.addEventListener("click", () => {
    dom.libraryModal?.classList.add("hidden");
  });

  dom.selectCurrentDirBtn?.addEventListener("click", () => {
    const currentDir = state.pickerCurrentPath || "/";
    if (currentDir === "/") {
      showPickerStatus(
        t("Open or check a subfolder below to add it to your library.")
      );
      return;
    }
    showPickerStatus("");
    state.pickerSelectedPaths.add(currentDir);
    updatePickerSelectionCount(dom, state);
  });

  dom.addSelectedDirsBtn?.addEventListener("click", async () => {
    try {
      showPickerStatus("");
      await addSelectedFoldersToLibrary(state);
      dom.libraryModal?.classList.add("hidden");
      await loadRoots();
    } catch (err) {
      showPickerStatus(err.message);
    }
  });

  dom.brandHome?.addEventListener("click", () => {
    state.isSearching = false;
    state.searchQuery = "";
    if (dom.searchInput) {
      dom.searchInput.value = "";
    }
    dom.clearSearchBtn?.classList.add("hidden");
    state.currentPath = "/";
    loadCurrentFolder();
  });

  dom.alertRetryBtn?.addEventListener("click", () => {
    loadRoots();
  });

  dom.sortSelect?.addEventListener("change", async () => {
    await savePreferencesToServer(state, { sortBy: dom.sortSelect.value });
    await loadCurrentFolder();
  });

  let searchDebounce = null;
  dom.searchInput?.addEventListener("input", () => {
    if (searchDebounce) {
      clearTimeout(searchDebounce);
    }
    const q = dom.searchInput.value;
    searchDebounce = setTimeout(() => {
      runSearch(q);
    }, 280);
  });

  const clearSearchHandler = () => {
    if (dom.searchInput) {
      dom.searchInput.value = "";
    }
    dom.clearSearchBtn?.classList.add("hidden");
    state.isSearching = false;
    state.searchQuery = "";
    loadCurrentFolder();
  };

  dom.clearSearchBtn?.addEventListener("click", clearSearchHandler);
  dom.emptyResetSearchBtn?.addEventListener("click", clearSearchHandler);

  dom.openSessionsModalBtn?.addEventListener("click", async () => {
    dom.sessionsModal?.classList.remove("hidden");
    if (dom.sessionsStatusMsg) {
      dom.sessionsStatusMsg.classList.add("hidden");
    }
    try {
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
    } catch (err) {
      if (dom.sessionsStatusMsg) {
        dom.sessionsStatusMsg.textContent = err.message;
        dom.sessionsStatusMsg.classList.remove("hidden");
      }
    }
  });

  dom.closeSessionsModalBtn?.addEventListener("click", () => {
    dom.sessionsModal?.classList.add("hidden");
  });

  window.addEventListener("pagehide", () => {
    sessionManager.closeCurrentSession({ useBeacon: true });
  });

  // Load initial preferences and roots after all UI listeners are active
  await syncPreferencesFromServer(state);
  if (dom.sortSelect) {
    dom.sortSelect.value = state.prefs.sortBy;
  }
  await loadRoots();
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
})();
