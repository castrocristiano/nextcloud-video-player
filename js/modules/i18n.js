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

export function translate(text, vars = {}) {
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

export function translatePlural(singular, plural, count, vars = {}) {
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

export function getLocale() {
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

export const t = translate;
export const n = translatePlural;
