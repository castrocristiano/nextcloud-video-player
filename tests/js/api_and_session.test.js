import { describe, it, expect, vi, beforeEach } from "vitest";
import {
  apiDelete,
  apiGet,
  apiPost,
  apiPut,
  buildAppUrl,
  getRequestToken,
  sendSessionCloseBeacon,
} from "../../js/modules/api.js";
import { PlaybackSessionManager } from "../../js/modules/session.js";
import { createAppState } from "../../js/modules/state.js";

describe("api and session manager modules", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    window.OC = {
      requestToken: "csrf_tok_123",
      generateUrl: (p) => `/nc${p}`,
    };
  });

  it("builds URLs, attaches CSRF token, and handles HTTP errors", async () => {
    expect(getRequestToken()).toBe("csrf_tok_123");
    expect(buildAppUrl("/api/v1/roots", { sort: "name_asc", empty: "" })).toBe(
      "/nc/apps/folder_video_player/api/v1/roots?sort=name_asc"
    );

    globalThis.fetch = vi.fn().mockResolvedValueOnce({
      ok: false,
      status: 429,
      json: async () => ({
        error: {
          message: "Maximum number of concurrent playback sessions reached",
          maxConcurrentSessions: 2,
        },
      }),
    });

    await expect(apiPost("/api/v1/sessions", {})).rejects.toMatchObject({
      status: 429,
      message: "Maximum number of concurrent playback sessions reached",
    });
  });

  it("manages playback session lifecycle, heartbeats, beacon close, and revocation", async () => {
    const calls = [];
    globalThis.fetch = vi.fn().mockImplementation(async (url, opts) => {
      calls.push({ url, method: opts?.method, keepalive: opts?.keepalive });
      if (url.includes("/api/v1/sessions") && opts?.method === "POST" && !url.includes("/close")) {
        return {
          ok: true,
          status: 201,
          json: async () => ({
            sessionToken: "sess_abc",
            progress: {
              currentTime: 42,
              duration: 200,
              progressRatio: 0.21,
              completed: false,
            },
          }),
        };
      }
      if (url.includes("/heartbeat")) {
        return {
          ok: true,
          status: 200,
          json: async () => ({
            sessionToken: "sess_abc",
            status: "active",
            progress: {
              currentTime: 60,
              duration: 200,
              progressRatio: 0.3,
              completed: false,
            },
          }),
        };
      }
      return {
        ok: true,
        status: 200,
        json: async () => ({ sessions: [{ sessionToken: "sess_abc" }] }),
      };
    });

    const state = createAppState();
    const video = {
      id: "rk1:/Movie.mp4",
      rootId: "rk1",
      relativePath: "/Movie.mp4",
    };
    state.currentVideo = video;

    const manager = new PlaybackSessionManager(state);
    const started = await manager.startSession(video, "direct");
    expect(started.sessionToken).toBe("sess_abc");
    expect(state.activeSessionToken).toBe("sess_abc");

    const hb = await manager.sendHeartbeat({
      currentTime: 60,
      duration: 200,
      status: "active",
    });
    expect(hb.status).toBe("active");
    expect(video.progress.currentTime).toBe(60);

    const active = await manager.fetchActiveSessions();
    expect(active.sessions).toHaveLength(1);

    await manager.closeCurrentSession({
      currentTime: 90,
      duration: 200,
      useBeacon: true,
    });
    expect(state.activeSessionToken).toBe("");
    expect(sendSessionCloseBeacon("", {})).toBe(false);

    // Direct apiGet / apiPut / apiDelete coverage
    await apiGet("/api/v1/roots");
    await apiPut("/api/v1/preferences", { playbackRate: 1.0 });
    await apiDelete("/api/v1/roots/rk1");
    await manager.revokeSession("other_tok");
    expect(calls.length).toBeGreaterThanOrEqual(6);
  });
});
