import { apiGet, apiPut } from "./api.js";
import { computeProgressEntry } from "./formatters.js";

const PREFS_CACHE_KEY = "ncvp:prefs";
const PROGRESS_CACHE_PREFIX = "ncvp:progress:";

export function getDefaultPreferences() {
  return {
    playbackRate: 1.0,
    autoPlayNext: true,
    sortBy: "name_asc",
    volume: 1.0,
    muted: false,
    preferredSubtitle: null,
  };
}

export function createAppState() {
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
    subtitleSyncOffset: 0,
    totalDuration: 0,
    isDraggingSeek: false,
    // Folder Picker state
    pickerCurrentPath: "/",
    pickerSelectedPaths: new Set(),
  };
}

export function loadCachedPreferences() {
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

export function cachePreferencesLocally(prefs) {
  try {
    localStorage.setItem(PREFS_CACHE_KEY, JSON.stringify(prefs));
  } catch {
    // Ignore storage quota errors
  }
}

export async function syncPreferencesFromServer(state) {
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

export async function savePreferencesToServer(state, partialPrefs = {}) {
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

export function getVideoProgress(state, video) {
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

export function recordLocalVideoProgress(
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
