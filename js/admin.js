(function () {
  "use strict";

  const APP_ID = "nextcloud_video_player";

  function interpolate(template, vars) {
    if (!vars || typeof vars !== "object") {
      return template;
    }
    return String(template).replace(/\{(\w+)\}/g, (match, key) =>
      Object.prototype.hasOwnProperty.call(vars, key) ? String(vars[key]) : match
    );
  }

  function t(text, vars) {
    if (typeof window !== "undefined") {
      if (typeof window.t === "function") {
        const res = window.t(APP_ID, text, vars);
        if (typeof res === "string" && res.length > 0) {
          return interpolate(res, vars);
        }
      }
      if (window.OC?.L10N && typeof window.OC.L10N.translate === "function") {
        const res = window.OC.L10N.translate(APP_ID, text, vars);
        if (typeof res === "string" && res.length > 0) {
          return interpolate(res, vars);
        }
      }
    }
    return interpolate(text, vars);
  }

  function buildAppUrl(path) {
    const cleanPath = path.startsWith("/") ? path : `/${path}`;
    if (typeof window !== "undefined" && typeof window.OC?.generateUrl === "function") {
      return window.OC.generateUrl(`/apps/${APP_ID}${cleanPath}`);
    }
    return `/index.php/apps/${APP_ID}${cleanPath}`;
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

  function parseGlobalFoldersTextarea(rawText) {
    return String(rawText || "")
      .split(/\r?\n/)
      .map((l) => l.trim())
      .filter(Boolean)
      .map((line) => {
        const parts = line.split("|");
        return {
          path: parts[0].trim(),
          label: parts.slice(1).join("|").trim(),
        };
      });
  }

  function bootAdminSettings() {
    const form = document.getElementById("ncvpAdminForm");
    if (!form) {
      return;
    }

    const foldersInput = document.getElementById("ncvpGlobalFoldersInput");
    const ffmpegCheckbox = document.getElementById("ncvpFfmpegEnabled");
    const ffmpegPathInput = document.getElementById("ncvpFfmpegPath");
    const ffprobePathInput = document.getElementById("ncvpFfprobePath");
    const sessionTtlInput = document.getElementById("ncvpSessionTtl");
    const maxConcurrentInput = document.getElementById("ncvpMaxConcurrent");
    const statusEl = document.getElementById("ncvpAdminStatus");

    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      if (statusEl) {
        statusEl.textContent = t("Saving...");
      }

      try {
        const globalFolders = parseGlobalFoldersTextarea(foldersInput?.value);
        const token = getRequestToken();
        const resp = await fetch(buildAppUrl("/api/v1/admin/settings"), {
          method: "PUT",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            ...(token ? { requesttoken: token } : {}),
          },
          credentials: "same-origin",
          body: JSON.stringify({
            globalFolders,
            ffmpegEnabled: Boolean(ffmpegCheckbox?.checked),
            ffmpegPath: ffmpegPathInput?.value || "ffmpeg",
            ffprobePath: ffprobePathInput?.value || "ffprobe",
            sessionTtlSeconds: Number(sessionTtlInput?.value) || 180,
            maxConcurrentSessions: Number(maxConcurrentInput?.value) || 0,
          }),
        });
        const data = await resp.json().catch(() => null);
        if (!resp.ok) {
          throw new Error(data?.error?.message || `HTTP Error ${resp.status}`);
        }
        if (statusEl) {
          statusEl.textContent = t("Settings saved successfully.");
        }
      } catch (err) {
        if (statusEl) {
          statusEl.textContent = err.message || t("Failed to save settings.");
        }
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootAdminSettings);
  } else {
    bootAdminSettings();
  }
})();
