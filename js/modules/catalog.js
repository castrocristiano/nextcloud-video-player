import { buildAppUrl } from "./api.js";
import { formatBytes, formatDate, formatDuration } from "./formatters.js";
import { t } from "./i18n.js";
import { getVideoProgress } from "./state.js";

export function renderContinueWatching(dom, state, onResumeLastWatched) {
  if (!dom.continueWatchingBanner) {
    return;
  }
  const lw = state.lastWatched;
  if (!lw || !lw.rootId || !lw.relativePath) {
    dom.continueWatchingBanner.classList.add("hidden");
    return;
  }

  if (dom.continueWatchingTitle) {
    dom.continueWatchingTitle.textContent = lw.name || lw.relativePath;
  }
  if (dom.continueWatchingMeta) {
    const curStr = formatDuration(lw.currentTime || 0);
    const durStr = lw.duration > 0 ? ` / ${formatDuration(lw.duration)}` : "";
    dom.continueWatchingMeta.textContent = `${lw.rootLabel || ""} • ${curStr}${durStr}`;
  }
  if (dom.continueWatchingBtn) {
    dom.continueWatchingBtn.onclick = () => onResumeLastWatched(lw);
  }
  dom.continueWatchingBanner.classList.remove("hidden");
}

export function renderRootsTabs(dom, state, onSelectRoot) {
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

export function renderBreadcrumbs(dom, state, onNavigatePath) {
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

export function renderCatalog(dom, state, { onOpenFolder, onPlayVideo }) {
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
