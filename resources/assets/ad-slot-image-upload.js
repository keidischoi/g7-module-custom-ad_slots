/*! custom-ad_slots — admin FileUploader assist (upload_token + hydrate + URL sync), v1.4.23
 * Visible control is layout FileUploader (maker_bids pattern). This script:
 *  - ensures form.upload_token exists (fallback if form-defaults slow)
 *  - hydrates image_url* Inputs + _local.form from GET /admin/ads/:id (edit load)
 *  - observes upload XHR/fetch success and fills ONLY the matching image_url* field
 *  - does NOT mount native <input type=file>
 * Collections are per-field (ad_slot_image_url*); do not write one URL into sibling fields.
 */
(function () {
  var CAS_UPLOAD_VERSION = "1.4.23";
  if (window.__casAdUploadVersion === CAS_UPLOAD_VERSION) return;
  window.__casAdUploadVersion = CAS_UPLOAD_VERSION;

  var FIELDS = ["image_url", "image_url_desktop", "image_url_mobile"];
  var UPLOAD_PATH = "/api/modules/custom-ad_slots/admin/uploads";
  var ADS_PATH = "/api/modules/custom-ad_slots/admin/ads/";

  function isAdSlotFormPage() {
    var p = String(location.pathname || "");
    return /\/admin\/ad-slots\/(create|\d+\/edit)\/?$/.test(p) ||
      /\/admin\/.*ad[_-]?slots.*(create|edit)/i.test(p);
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

  function setUrlField(field, url, opts) {
    if (FIELDS.indexOf(field) < 0) return;
    url = url == null ? "" : String(url);
    opts = opts || {};
    var input = findUrlInput(field);
    if (input) {
      try {
        var setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, "value");
        if (setter && setter.set) setter.set.call(input, url);
        else input.value = url;
      } catch (e) {
        input.value = url;
      }
      // Avoid dispatching change on hydrate — empty change would DELETE/forget remembered URLs.
      if (!opts.silent) {
        try {
          input.dispatchEvent(new Event("input", { bubbles: true }));
          input.dispatchEvent(new Event("change", { bubbles: true }));
        } catch (e2) {}
      }
    }
    var patch = {};
    patch["form." + field] = url;
    if (url) {
      patch["form." + field + "_count"] = 1;
    }
    dispatchSetState(patch);
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

  function extractFieldFromUrl(url) {
    try {
      var u = new URL(url, location.origin);
      var f = u.searchParams.get("field");
      if (f && FIELDS.indexOf(f) >= 0) return f;
    } catch (e) {}
    // Fallback: query string without URL parser
    try {
      var m = String(url).match(/[?&]field=(image_url(?:_desktop|_mobile)?)\b/);
      if (m && FIELDS.indexOf(m[1]) >= 0) return m[1];
    } catch (e2) {}
    return "";
  }

  function extractFieldFromBody(body) {
    try {
      if (!body) return "";
      if (typeof FormData !== "undefined" && body instanceof FormData) {
        var keys = [
          "field",
          "uploadParams.field",
          "params.field",
          "upload_params.field",
          "uploadParams[field]",
          "params[field]",
        ];
        for (var i = 0; i < keys.length; i++) {
          var f = body.get(keys[i]);
          if (f && FIELDS.indexOf(String(f)) >= 0) return String(f);
        }
        // Some runtimes expose entries only
        if (typeof body.entries === "function") {
          var it = body.entries();
          var step;
          while (!(step = it.next()).done) {
            var k = String(step.value[0] || "");
            var v = step.value[1];
            if (
              (k === "field" || /(?:^|[.\[\]])field(?:\]|$)/.test(k)) &&
              v &&
              FIELDS.indexOf(String(v)) >= 0
            ) {
              return String(v);
            }
          }
        }
      }
      if (typeof body === "string" && body.indexOf("field=") >= 0) {
        var m = body.match(/(?:^|&)field=(image_url(?:_desktop|_mobile)?)\b/);
        if (m) return m[1];
      }
    } catch (e) {}
    return "";
  }

  function ensureUploadToken() {
    if (!isAdSlotFormPage()) return;
    try {
      var existing = "";
      var tokenInput = document.querySelector('input[name="upload_token"]');
      if (tokenInput && tokenInput.value) existing = String(tokenInput.value).trim();
      if (existing) return;
      var token =
        (window.crypto && crypto.randomUUID && crypto.randomUUID().replace(/-/g, "")) ||
        Array.from({ length: 32 }, function () {
          return Math.floor(Math.random() * 16).toString(16);
        }).join("");
      dispatchSetState({ "form.upload_token": token });
    } catch (e) {}
  }

  function pickAdPayload(json) {
    if (!json || typeof json !== "object") return null;
    // success() → { data: { id, image_url, ... } } or nested { data: { data: {...} } }
    var d = json.data;
    if (d && typeof d === "object") {
      if (d.id != null && (d.image_url !== undefined || d.uploader_image_url !== undefined || d.slot_key)) {
        return d;
      }
      if (d.data && typeof d.data === "object" && d.data.id != null) {
        return d.data;
      }
    }
    if (json.id != null && (json.image_url !== undefined || json.slot_key)) return json;
    return null;
  }

  function hydrateFromAdPayload(payload) {
    if (!payload || typeof payload !== "object") return;
    var patch = {};
    if (payload.id != null) patch["form.id"] = payload.id;
    FIELDS.forEach(function (field) {
      var raw = payload[field];
      var url = raw == null ? "" : String(raw).trim();
      // Only hydrate non-empty DB URLs. Empty/null must NOT overwrite a staged
      // upload already in the Input / _local.form (GET refetch after mobile upload).
      if (!url) return;
      setUrlField(field, url, { silent: true });
      var upKey = "uploader_" + field;
      if (payload[upKey] && payload[upKey].length) {
        patch["form." + upKey] = payload[upKey];
        patch["form." + field + "_count"] = 1;
      } else {
        patch["form." + field + "_count"] = 1;
      }
    });
    if (Object.keys(patch).length) dispatchSetState(patch);
  }

  function handleUploadResponse(requestUrl, json, bodyField) {
    if (!json) return;
    var field = extractFieldFromUrl(requestUrl) || bodyField || "";
    var url = extractUrl(json);
    if (!field || !url) return;
    setUrlField(field, url, { silent: false });
  }

  function handleAdGetResponse(requestUrl, json) {
    if (!isAdSlotFormPage()) return;
    if (!requestUrl || requestUrl.indexOf(ADS_PATH) < 0) return;
    // Only single-ad GET: /admin/ads/123 (not /admin/ads or /admin/ads?…)
    if (!/\/admin\/ads\/\d+(\?|$)/.test(String(requestUrl))) return;
    var payload = pickAdPayload(json);
    if (!payload) return;
    hydrateFromAdPayload(payload);
  }

  function patchFetch() {
    if (window.__casFetchPatched) return;
    window.__casFetchPatched = true;
    if (typeof window.fetch !== "function") return;
    var orig = window.fetch;
    window.fetch = function () {
      var args = arguments;
      var reqUrl = "";
      var bodyField = "";
      var method = "GET";
      try {
        if (typeof args[0] === "string") reqUrl = args[0];
        else if (args[0] && args[0].url) reqUrl = args[0].url;
        if (args[1] && args[1].body) bodyField = extractFieldFromBody(args[1].body);
        method = (args[1] && args[1].method) || (args[0] && args[0].method) || "GET";
      } catch (e) {}
      return orig.apply(this, args).then(function (res) {
        try {
          var m = String(method).toUpperCase();
          if (reqUrl && reqUrl.indexOf(UPLOAD_PATH) >= 0 && m === "POST") {
            if (reqUrl.indexOf("/noop") < 0 && reqUrl.indexOf("/remembered") < 0) {
              res.clone().json().then(function (js) {
                if (res.ok) handleUploadResponse(reqUrl, js, bodyField);
              }).catch(function () {});
            }
          } else if (reqUrl && m === "GET" && /\/admin\/ads\/\d+/.test(reqUrl)) {
            res.clone().json().then(function (js) {
              if (res.ok) handleAdGetResponse(reqUrl, js);
            }).catch(function () {});
          }
        } catch (e2) {}
        return res;
      });
    };
  }

  function patchXHR() {
    if (window.__casXhrPatched) return;
    window.__casXhrPatched = true;
    var open = XMLHttpRequest.prototype.open;
    var send = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function (method, url) {
      this.__casMethod = String(method || "");
      this.__casUrl = String(url || "");
      return open.apply(this, arguments);
    };
    XMLHttpRequest.prototype.send = function (body) {
      var xhr = this;
      var bodyField = extractFieldFromBody(body);
      var isUploadPost =
        xhr.__casUrl &&
        xhr.__casUrl.indexOf(UPLOAD_PATH) >= 0 &&
        String(xhr.__casMethod).toUpperCase() === "POST" &&
        xhr.__casUrl.indexOf("/noop") < 0;
      var isAdGet =
        xhr.__casUrl &&
        String(xhr.__casMethod).toUpperCase() === "GET" &&
        /\/admin\/ads\/\d+/.test(xhr.__casUrl);
      if (isUploadPost || isAdGet) {
        xhr.addEventListener("load", function () {
          if (xhr.status < 200 || xhr.status >= 300) return;
          try {
            var js = JSON.parse(xhr.responseText || "{}");
            if (isUploadPost) handleUploadResponse(xhr.__casUrl, js, bodyField);
            if (isAdGet) handleAdGetResponse(xhr.__casUrl, js);
          } catch (e) {}
        });
      }
      return send.apply(this, arguments);
    };
  }

  function boot() {
    if (!isAdSlotFormPage()) return;
    ensureUploadToken();
  }

  patchFetch();
  patchXHR();

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
})();
