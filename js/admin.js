import { apiPut } from "./modules/api.js";
import { t } from "./modules/i18n.js";

export function parseGlobalFoldersTextarea(rawText) {
  const lines = String(rawText || "")
    .split(/\r?\n/)
    .map((l) => l.trim())
    .filter(Boolean);

  return lines.map((line) => {
    const parts = line.split("|");
    const path = parts[0].trim();
    const label = parts.slice(1).join("|").trim();
    return { path, label };
  });
}

export function bootAdminSettings() {
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
      await apiPut("/api/v1/admin/settings", {
        globalFolders,
        ffmpegEnabled: Boolean(ffmpegCheckbox?.checked),
        ffmpegPath: ffmpegPathInput?.value || "ffmpeg",
        ffprobePath: ffprobePathInput?.value || "ffprobe",
        sessionTtlSeconds: Number(sessionTtlInput?.value) || 180,
        maxConcurrentSessions: Number(maxConcurrentInput?.value) || 0,
      });

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

if (typeof document !== "undefined") {
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootAdminSettings);
  } else {
    bootAdminSettings();
  }
}
