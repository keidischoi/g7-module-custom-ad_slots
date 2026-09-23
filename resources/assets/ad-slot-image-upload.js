/*! custom-ad_slots — admin native image upload (URL input + preview + clear) */
(function () {
  var CAS_UPLOAD_VERSION = "1.4.19";
  if (window.__casAdUploadVersion === CAS_UPLOAD_VERSION) return;
  window.__casAdUploadVersion = CAS_UPLOAD_VERSION;

  var FIELDS = ["image_url", "image_url_desktop", "image_url_mobile"];
  var UPLOAD_URL = "/api/modules/custom-ad_slots/admin/uploads";
  var MAX_BYTES = 5 * 1024 * 1024;
  var ACCEPT = "image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp";

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.getAttribute("content")) return String(meta.getAttribute("content"));
    var inp = document.querySelector('input[name="_token"]');
    if (inp && inp.value) return String(inp.value);
    try {
      if (window.G7Core && window.G7Core.csrf) return String(window.G7Core.csrf);
    } catch (e) {}
    return "";
  }

  function authToken() {
    try {
      var t = localStorage.getItem("auth_token") || localStorage.getItem("access_token") || "";
      if (t) return String(t);
    } catch (e) {}
    try {
      if (window.G7Core && window.G7Core.api) {
        if (typeof window.G7Core.api.getToken === "function") {
          var g = window.G7Core.api.getToken();
          if (g) return String(g);
        }
        if (window.G7Core.api.token) return String(window.G7Core.api.token);
      }
    } catch (e2) {}
    return "";
  }

  function authHeaders() {
    var h = { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" };
    var csrf = csrfToken();
    if (csrf) h["X-CSRF-TOKEN"] = csrf;
    try {
      var m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
      if (m) h["X-XSRF-TOKEN"] = decodeURIComponent(m[1]);
    } catch (e) {}
    var at = authToken();
    if (at) h["Authorization"] = "Bearer " + at;
    return h;
  }

  function isAdSlotFormPage() {
    var p = String(location.pathname || "");
    return /\/admin\/ad-slots\/(create|\d+\/edit)\/?$/.test(p);
  }

  function setStatus(el, text, isError) {
    if (!el) return;
    el.textContent = text || "";
    el.style.color = isError ? "#b91c1c" : "#6b7280";
  }

  function dispatchSetState(partial) {
    try {
      if (window.G7Core && typeof window.G7Core.dispatch === "function") {
        window.G7Core.dispatch({
          handler: "setState",
          params: Object.assign({ target: "local" }, partial),
        });
        return true;
      }
    } catch (e) {}
    return false;
  }

  function findUrlInput(field) {
    var nodes = document.querySelectorAll('input[name="' + field + '"]');
    if (!nodes.length) return null;
    return nodes[nodes.length - 1];
  }

  function setUrlField(field, url, uploadId) {
    url = url == null ? "" : String(url);
    var input = findUrlInput(field);
    if (input) {
      try {
        var setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, "value");
        if (setter && setter.set) setter.set.call(input, url);
        else input.value = url;
      } catch (e) {
        input.value = url;
      }
      try {
        input.dispatchEvent(new Event("input", { bubbles: true }));
        input.dispatchEvent(new Event("change", { bubbles: true }));
      } catch (e2) {}
    }

    var patch = {};
    patch["form." + field] = url;
    dispatchSetState(patch);

    var mount = document.querySelector('[data-cas-ad-upload-field="' + field + '"]');
    if (mount) {
      if (uploadId) mount.setAttribute("data-cas-upload-id", String(uploadId));
      else mount.removeAttribute("data-cas-upload-id");
    }

    syncPreview(field, url);
  }

  function syncPreview(field, url) {
    var img = document.querySelector('[data-cas-ad-preview="' + field + '"]');
    var wrap = document.querySelector('[data-cas-ad-preview-wrap="' + field + '"]');
    var clearBtn = document.querySelector('[data-cas-ad-clear="' + field + '"]');
    url = (url || "").trim();
    if (img) {
      if (url) {
        img.setAttribute("src", url);
        img.style.display = "";
      } else {
        img.removeAttribute("src");
        img.style.display = "none";
      }
    }
    if (wrap) wrap.style.display = url ? "" : "none";
    if (clearBtn) clearBtn.style.display = url ? "" : "none";
  }

  function extractUrl(json) {
    if (!json || typeof json !== "object") return "";
    var d = json.data;
    if (d && typeof d === "object") {
      if (d.data && typeof d.data === "object") {
        var nested = d.data.download_url || d.data.url || "";
        if (nested) return String(nested);
      }
      if (d.download_url || d.url) return String(d.download_url || d.url);
    }
    return String(json.download_url || json.url || "");
  }

  function extractId(json) {
    if (!json || typeof json !== "object") return "";
    var d = json.data;
    if (d && typeof d === "object") {
      if (d.data && d.data.id) return String(d.data.id);
      if (d.id) return String(d.id);
    }
    return json.id ? String(json.id) : "";
  }

  function encodePathId(path) {
    try {
      var b64 = btoa(path).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, "");
      return b64;
    } catch (e) {
      return "";
    }
  }

  function idFromUrl(url) {
    var m = String(url || "").match(/(custom-ad_slots\/\d{4}\/\d{2}\/\d{2}\/[A-Za-z0-9._-]+)/);
    if (!m) return "";
    return encodePathId(m[1]);
  }

  function uploadFile(field, file, statusEl, fileInput) {
    if (!file) return;
    if (file.size > MAX_BYTES) {
      setStatus(statusEl, "Max 5MB", true);
      return;
    }
    setStatus(statusEl, "Uploading…", false);
    var fd = new FormData();
    fd.append("file", file);
    fd.append("field", field);

    fetch(UPLOAD_URL + "?field=" + encodeURIComponent(field), {
      method: "POST",
      credentials: "include",
      headers: authHeaders(),
      body: fd,
    })
      .then(function (res) {
        return res.json().then(function (js) {
          return { ok: res.ok, status: res.status, js: js };
        });
      })
      .then(function (r) {
        if (!r.ok) {
          var msg =
            (r.js && (r.js.message || r.js.error || (r.js.errors && JSON.stringify(r.js.errors)))) ||
            "Upload failed (" + r.status + ")";
          setStatus(statusEl, String(msg), true);
          return;
        }
        var url = extractUrl(r.js);
        var id = extractId(r.js) || idFromUrl(url);
        if (!url) {
          setStatus(statusEl, "Upload ok but no URL in response", true);
          return;
        }
        setUrlField(field, url, id);
        setStatus(statusEl, "Uploaded", false);
        if (fileInput) fileInput.value = "";
      })
      .catch(function (err) {
        setStatus(statusEl, (err && err.message) || "Upload failed", true);
      });
  }

  function clearField(field, statusEl) {
    var mount = document.querySelector('[data-cas-ad-upload-field="' + field + '"]');
    var uploadId =
      (mount && mount.getAttribute("data-cas-upload-id")) ||
      idFromUrl((findUrlInput(field) && findUrlInput(field).value) || "");
    setUrlField(field, "", "");
    setStatus(statusEl, "", false);

    var delId = uploadId || "noop";
    fetch(UPLOAD_URL + "/" + encodeURIComponent(delId) + "?field=" + encodeURIComponent(field), {
      method: "DELETE",
      credentials: "include",
      headers: authHeaders(),
    }).catch(function () {});
  }

  function ensureMount(field) {
    var mount = document.querySelector('[data-cas-ad-upload-field="' + field + '"]');
    if (!mount) return null;
    var existingFile = mount.querySelector('input[data-cas-ad-file="' + field + '"]');
    if (mount.getAttribute("data-cas-upload-ready") === "1" && existingFile) return mount;

    mount.setAttribute("data-cas-upload-ready", "1");
    mount.innerHTML = "";
    mount.style.display = "flex";
    mount.style.flexWrap = "wrap";
    mount.style.alignItems = "center";
    mount.style.gap = "0.5rem";
    mount.style.marginTop = "0.35rem";

    var file = document.createElement("input");
    file.type = "file";
    file.accept = ACCEPT;
    file.className = "input w-full max-w-md text-sm";
    file.setAttribute("data-cas-ad-file", field);

    var status = document.createElement("span");
    status.className = "text-xs";
    status.setAttribute("data-cas-ad-status", field);

    var clear = document.createElement("button");
    clear.type = "button";
    clear.textContent = "Clear";
    clear.className = "btn btn-secondary btn-sm text-xs";
    clear.setAttribute("data-cas-ad-clear", field);
    clear.style.display = "none";

    file.addEventListener("change", function () {
      var f = file.files && file.files[0];
      if (f) uploadFile(field, f, status, file);
    });
    clear.addEventListener("click", function (ev) {
      ev.preventDefault();
      clearField(field, status);
    });

    mount.appendChild(file);
    mount.appendChild(clear);
    mount.appendChild(status);

    // Layout may also render Img + clear; keep JS clear as fallback.
    var existingUrl = "";
    var input = findUrlInput(field);
    if (input && input.value) existingUrl = String(input.value).trim();
    if (existingUrl) {
      mount.setAttribute("data-cas-upload-id", idFromUrl(existingUrl) || "");
      syncPreview(field, existingUrl);
      clear.style.display = "";
    }

    return mount;
  }

  function syncFromInputs() {
    FIELDS.forEach(function (field) {
      var input = findUrlInput(field);
      if (!input) return;
      syncPreview(field, input.value || "");
      if (!input.__casUrlBound) {
        input.__casUrlBound = true;
        input.addEventListener("input", function () {
          syncPreview(field, input.value || "");
        });
        input.addEventListener("change", function () {
          syncPreview(field, input.value || "");
          // Empty typed URL → forget remember on server
          if (!(input.value || "").trim()) {
            fetch(UPLOAD_URL + "/noop?field=" + encodeURIComponent(field), {
              method: "DELETE",
              credentials: "include",
              headers: authHeaders(),
            }).catch(function () {});
          }
        });
      }
    });
  }

  function bindLayoutClearButtons() {
    FIELDS.forEach(function (field) {
      document.querySelectorAll('[data-cas-ad-clear-btn="' + field + '"]').forEach(function (btn) {
        if (btn.__casClearBound) return;
        btn.__casClearBound = true;
        btn.addEventListener("click", function (ev) {
          ev.preventDefault();
          var status = document.querySelector('[data-cas-ad-status="' + field + '"]');
          clearField(field, status);
        });
      });
    });
  }

  function boot() {
    if (!isAdSlotFormPage()) return;
    FIELDS.forEach(ensureMount);
    syncFromInputs();
    bindLayoutClearButtons();
  }

  var timer = null;
  function schedule() {
    if (timer) clearTimeout(timer);
    timer = setTimeout(boot, 50);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", schedule);
  } else {
    schedule();
  }
  window.addEventListener("popstate", schedule);
  try {
    var _push = history.pushState;
    var _replace = history.replaceState;
    history.pushState = function () {
      var r = _push.apply(this, arguments);
      schedule();
      return r;
    };
    history.replaceState = function () {
      var r = _replace.apply(this, arguments);
      schedule();
      return r;
    };
  } catch (eH) {}
  try {
    var mo = new MutationObserver(schedule);
    mo.observe(document.documentElement, { childList: true, subtree: true });
  } catch (eM) {}
  setInterval(function () {
    if (isAdSlotFormPage()) boot();
  }, 1500);
})();
