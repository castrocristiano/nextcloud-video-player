const APP_ID = "nextcloud_video_player";

export function buildAppUrl(path, queryParams = {}) {
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

export function getRequestToken() {
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

export function apiGet(path, query = {}) {
  return requestJson("GET", path, { query });
}

export function apiPost(path, body = {}, options = {}) {
  return requestJson("POST", path, { body, ...options });
}

export function apiPut(path, body = {}, options = {}) {
  return requestJson("PUT", path, { body, ...options });
}

export function apiDelete(path, query = {}) {
  return requestJson("DELETE", path, { query });
}

export function sendSessionCloseBeacon(sessionToken, payload = {}) {
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
