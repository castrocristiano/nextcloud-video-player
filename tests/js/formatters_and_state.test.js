import { describe, it, expect, vi, beforeEach } from "vitest";
import {
  computeProgressEntry,
  formatBytes,
  formatDate,
  formatDuration,
} from "../../js/modules/formatters.js";
import {
  createAppState,
  getDefaultPreferences,
  getVideoProgress,
  recordLocalVideoProgress,
  savePreferencesToServer,
  syncPreferencesFromServer,
} from "../../js/modules/state.js";

describe("formatters and state modules", () => {
  beforeEach(() => {
    localStorage.clear();
    vi.restoreAllMocks();
  });

  it("formats bytes, durations, and locale dates", () => {
    expect(formatBytes(0)).toBe("0 B");
    expect(formatBytes(512)).toBe("512 B");
    expect(formatBytes(1048576)).toBe("1.0 MB");
    expect(formatDuration(-5)).toBe("00:00");
    expect(formatDuration(65)).toBe("01:05");
    expect(formatDuration(3661)).toBe("1:01:01");
    expect(formatDate("")).toBe("");
    expect(formatDate("invalid-date")).toBe("");
    expect(formatDate("2026-11-21T15:00:00Z", "pt-BR")).toMatch(/21\/11\/2026/);
    expect(formatDate("2026-11-21T15:00:00Z", "en-US")).toMatch(/11\/21\/2026/);
  });

  it("computes progress and resets at 95% completion threshold", () => {
    expect(computeProgressEntry("", 10, 100)).toBeNull();
    const partial = computeProgressEntry("v1", 30, 100);
    expect(partial.completed).toBe(false);
    expect(partial.currentTime).toBe(30);
    expect(partial.progressRatio).toBe(0.3);

    const completed = computeProgressEntry("v1", 96, 100);
    expect(completed.completed).toBe(true);
    expect(completed.currentTime).toBe(0);
    expect(completed.progressRatio).toBe(1.0);
  });

  it("synchronizes preferences and progress with server and localStorage fallback", async () => {
    globalThis.fetch = vi.fn().mockImplementation(async (url, opts) => {
      if (opts?.method === "PUT") {
        return {
          ok: true,
          json: async () => ({
            preferences: {
              ...getDefaultPreferences(),
              playbackRate: 1.5,
              sortBy: "date_desc",
            },
          }),
        };
      }
      return {
        ok: true,
        json: async () => ({
          preferences: {
            ...getDefaultPreferences(),
            playbackRate: 1.25,
          },
        }),
      };
    });

    const state = createAppState();
    state.userId = "alice";

    await syncPreferencesFromServer(state);
    expect(state.prefs.playbackRate).toBe(1.25);

    await savePreferencesToServer(state, {
      playbackRate: 1.5,
      sortBy: "date_desc",
    });
    expect(state.prefs.playbackRate).toBe(1.5);
    expect(state.prefs.sortBy).toBe("date_desc");

    const video = {
      id: "rk1:/Film.mp4",
      rootId: "rk1",
      relativePath: "/Film.mp4",
    };
    const entry = recordLocalVideoProgress(state, video, 120, 600, false);
    expect(entry.currentTime).toBe(120);

    const loaded = getVideoProgress(state, video);
    expect(loaded.currentTime).toBe(120);
  });
});
