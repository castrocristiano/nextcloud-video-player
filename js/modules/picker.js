import { apiDelete, apiGet, apiPost } from "./api.js";
import { n, t } from "./i18n.js";

export function renderActiveRootsInModal(dom, state, onRemoveRoot) {
  if (!dom.activeRootsList) {
    return;
  }
  dom.activeRootsList.innerHTML = "";

  if (!state.roots || state.roots.length === 0) {
    const li = document.createElement("li");
    li.className = "ncvp-root-item";
    li.textContent = t("No folders configured yet.");
    dom.activeRootsList.appendChild(li);
    return;
  }

  for (const root of state.roots) {
    const li = document.createElement("li");
    li.className = "ncvp-root-item";

    const info = document.createElement("div");
    const strong = document.createElement("strong");
    strong.textContent = root.label;
    const sub = document.createElement("span");
    sub.textContent = ` (${root.path})`;
    info.appendChild(strong);
    info.appendChild(sub);
    li.appendChild(info);

    if (root.scope === "global") {
      const badge = document.createElement("span");
      badge.className = "ncvp-badge";
      badge.textContent = t("Pre-configured by Admin");
      li.appendChild(badge);
    } else {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "ncvp-btn ncvp-btn-danger ncvp-btn-sm";
      btn.textContent = t("Remove");
      btn.addEventListener("click", () => onRemoveRoot(root.id));
      li.appendChild(btn);
    }

    dom.activeRootsList.appendChild(li);
  }
}

export function updatePickerSelectionCount(dom, state) {
  const count = state.pickerSelectedPaths.size;
  if (dom.pickerSelectedCount) {
    dom.pickerSelectedCount.textContent = n(
      "%n folder selected",
      "%n folders selected",
      count
    );
  }
  if (dom.addSelectedDirsBtn) {
    dom.addSelectedDirsBtn.disabled = count === 0;
  }
}

export async function loadPickerDirectory(dom, state, path = "/") {
  const listing = await apiGet("/api/v1/picker", { path });
  state.pickerCurrentPath = listing.currentPath || "/";

  if (dom.pickerBreadcrumbsList) {
    dom.pickerBreadcrumbsList.innerHTML = "";
    for (const crumb of listing.breadcrumbs || []) {
      const li = document.createElement("li");
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "ncvp-breadcrumb-btn";
      btn.textContent = crumb.label;
      btn.addEventListener("click", () => loadPickerDirectory(dom, state, crumb.path));
      li.appendChild(btn);
      dom.pickerBreadcrumbsList.appendChild(li);
    }
  }

  if (dom.pickerDirsList) {
    dom.pickerDirsList.innerHTML = "";
    const dirs = listing.directories || [];
    if (dirs.length === 0) {
      const emptyLi = document.createElement("li");
      emptyLi.className = "ncvp-picker-dir-item";
      emptyLi.textContent = t("No subfolders inside this directory.");
      dom.pickerDirsList.appendChild(emptyLi);
    } else {
      for (const dir of dirs) {
        const li = document.createElement("li");
        li.className = "ncvp-picker-dir-item";

        const label = document.createElement("label");
        label.className = "ncvp-checkbox-label";
        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.checked = state.pickerSelectedPaths.has(dir.path);
        checkbox.addEventListener("change", () => {
          if (checkbox.checked) {
            state.pickerSelectedPaths.add(dir.path);
          } else {
            state.pickerSelectedPaths.delete(dir.path);
          }
          updatePickerSelectionCount(dom, state);
        });

        const nameSpan = document.createElement("span");
        nameSpan.textContent = ` ${dir.name}`;
        label.appendChild(checkbox);
        label.appendChild(nameSpan);

        const openBtn = document.createElement("button");
        openBtn.type = "button";
        openBtn.className = "ncvp-btn ncvp-btn-secondary ncvp-btn-sm";
        openBtn.textContent = t("Open");
        openBtn.addEventListener("click", () =>
          loadPickerDirectory(dom, state, dir.path)
        );

        li.appendChild(label);
        li.appendChild(openBtn);
        dom.pickerDirsList.appendChild(li);
      }
    }
  }

  updatePickerSelectionCount(dom, state);
}

export async function addSelectedFoldersToLibrary(state) {
  const paths = Array.from(state.pickerSelectedPaths);
  if (paths.length === 0) {
    return state.roots;
  }
  const res = await apiPost("/api/v1/roots", { paths });
  state.pickerSelectedPaths.clear();
  state.roots = res.roots || [];
  return state.roots;
}

export async function removeUserRootFromLibrary(state, rootId) {
  const res = await apiDelete(`/api/v1/roots/${encodeURIComponent(rootId)}`);
  state.roots = res.roots || [];
  return state.roots;
}
