import { apiDelete, apiGet, apiPost, apiPut, sendSessionCloseBeacon } from "./api.js";
import { recordLocalVideoProgress } from "./state.js";

export const HEARTBEAT_INTERVAL_MS = 15000;

export class PlaybackSessionManager {
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
