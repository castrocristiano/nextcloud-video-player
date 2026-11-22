import { getLocale } from "./i18n.js";

export const COMPLETION_THRESHOLD = 0.95;

export function formatBytes(bytes) {
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

export function formatDate(isoStr, localeOverride = null) {
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

export function formatDuration(seconds) {
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

export function computeProgressEntry(videoId, currentTime, duration, forceCompleted = false) {
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
