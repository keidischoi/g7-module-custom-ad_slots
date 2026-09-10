/*! custom-ad_slots — Bunjang-style hero carousel (API-fed sibling host; React-safe) */
(function () {
  if (window.__casHeroCarouselInstalled) return;
  window.__casHeroCarouselInstalled = true;

  var INTERVAL_MS = 4000;
  var SWIPE_MIN = 40;
  var MD_MQ = "(min-width: 768px)";
  var PLACEMENTS_URL = "/api/modules/custom-ad_slots/placements";

  /** @type {Object.<string, {status:string, items:Array, promise:Promise|null, error:*} >} */
  var slotCache = {};

  function isExternal(url) {
    return /^https?:\/\//i.test(url || "");
  }

  function navigate(path) {
    try {
      if (window.G7Core && typeof window.G7Core.dispatch === "function") {
        window.G7Core.dispatch({ handler: "navigate", params: { path: path } });
        return;
      }
    } catch (e) {}
    try {
      location.assign(path);
    } catch (e2) {}
  }

  function truthyFlag(val) {
    return val === "1" || val === "true" || val === true || val === 1;
  }

  function resolveSlotKey(el) {
    if (!el || el.nodeType !== 1) return null;
    var attr =
      el.getAttribute("data-cas-hero-slot") ||
      (el.dataset && el.dataset.casHeroSlot) ||
      "";
    if (attr) return String(attr).trim();
    if (el.id === "ad_home_top_wrap") return "home.top";
    if (el.id === "ad_global_top_hero") return "global.top";
    if (el.id === "ad_global_top_wrap") return "global.top";
    var wrap = el.closest && el.closest("#ad_global_top_wrap");
    if (wrap) return "global.top";
    var home = el.closest && el.closest("#ad_home_top_wrap");
    if (home) return "home.top";
    return null;
  }

  function findMounts() {
    var found = [];

    function add(el) {
      if (!el || el.nodeType !== 1) return;
      if (el.getAttribute("data-cas-hero-host") === "1") return;
      if (el.classList && el.classList.contains("cas-hero-host")) return;
      for (var i = 0; i < found.length; i++) {
        if (found[i] === el) return;
      }
      found.push(el);
    }

    var home = document.getElementById("ad_home_top_wrap");
    if (home) add(home);

    var globalHero = document.getElementById("ad_global_top_hero");
    if (globalHero) {
      add(globalHero);
    } else {
      var globalWrap = document.getElementById("ad_global_top_wrap");
      if (globalWrap) {
        var inner =
          globalWrap.querySelector(".cas-hero") ||
          globalWrap.querySelector("[data-cas-hero]") ||
          globalWrap.querySelector("[data-cas-hero-slot]");
        if (inner) add(inner);
        else add(globalWrap);
      }
    }

    var bySlot = document.querySelectorAll("[data-cas-hero-slot]");
    for (var s = 0; s < bySlot.length; s++) add(bySlot[s]);

    var byClass = document.querySelectorAll(".cas-hero");
    for (var c = 0; c < byClass.length; c++) add(byClass[c]);

    var byAttr = document.querySelectorAll("[data-cas-hero]");
    for (var a = 0; a < byAttr.length; a++) add(byAttr[a]);

    return found;
  }

  function hideSourceRoot(root) {
    root.style.display = "none";
    root.setAttribute("aria-hidden", "true");
    if (!root.classList.contains("cas-hero-source")) {
      root.classList.add("cas-hero-source");
    }
  }

  function normalizePlacementsPayload(json) {
    if (!json) return [];
    var data = json.data !== undefined ? json.data : json;
    if (Array.isArray(data)) return data;
    if (data && Array.isArray(data.data)) return data.data;
    if (data && typeof data === "object") {
      // slot-grouped map → flatten unlikely for ?slot=; treat as empty
      return [];
    }
    return [];
  }

  function hasAnyImage(ad) {
    return !!(
      ad.image_desktop ||
      ad.image_url_desktop ||
      ad.image_url ||
      ad.image_mobile ||
      ad.image_url_mobile
    );
  }

  function mapApiItem(ad) {
    var desktop =
      ad.image_desktop || ad.image_url_desktop || ad.image_url || "";
    var mobile =
      ad.image_mobile ||
      ad.image_url_mobile ||
      ad.image_desktop ||
      ad.image_url_desktop ||
      ad.image_url ||
      "";
    var openNew = ad.open_in_new_tab;
    if (openNew === undefined || openNew === null) openNew = true;
    openNew = !!openNew;
    var prevent = !!(ad.prevent_right_click || ad.preventRightClick);
    var href = (ad.link_url || "").trim();
    var target = "";
    var rel = "";
    if (href && isExternal(href)) {
      if (openNew) {
        target = "_blank";
        rel = "noopener noreferrer";
      } else {
        target = "_self";
      }
    }
    return {
      id: ad.id,
      desktopSrc: desktop,
      mobileSrc: mobile || desktop,
      href: href,
      target: target,
      rel: rel,
      openInNewTab: openNew,
      title: ad.title || "",
      bg_color: ad.bg_color || "",
      preventRightClick: prevent,
    };
  }

  function filterAndMapItems(items) {
    var list = Array.isArray(items) ? items : [];
    var out = [];
    for (var i = 0; i < list.length; i++) {
      var ad = list[i];
      if (!ad) continue;
      var t = ad.type != null ? ad.type : "static";
      if (t !== "static") continue;
      if (!hasAnyImage(ad)) continue;
      out.push(mapApiItem(ad));
    }
    return out;
  }

  function fetchSlot(slotKey) {
    if (!slotKey) {
      return Promise.resolve({ status: "err", items: [], error: "no-slot" });
    }
    var cached = slotCache[slotKey];
    if (cached && (cached.status === "ok" || cached.status === "err")) {
      return Promise.resolve(cached);
    }
    if (cached && cached.status === "pending" && cached.promise) {
      return cached.promise;
    }

    var entry = { status: "pending", items: [], promise: null, error: null };
    slotCache[slotKey] = entry;

    entry.promise = fetch(
      PLACEMENTS_URL + "?slot=" + encodeURIComponent(slotKey),
      { credentials: "same-origin", headers: { Accept: "application/json" } }
    )
      .then(function (res) {
        if (!res.ok) throw new Error("HTTP " + res.status);
        return res.json();
      })
      .then(function (json) {
        var raw = normalizePlacementsPayload(json);
        entry.items = filterAndMapItems(raw);
        entry.status = "ok";
        entry.error = null;
        return entry;
      })
      .catch(function (err) {
        entry.status = "err";
        entry.items = [];
        entry.error = err;
        return entry;
      });

    return entry.promise;
  }

  function pickDesktopMobile(imgs) {
    var list = Array.prototype.slice.call(imgs || []);
    var desktop = null;
    var mobile = null;

    for (var i = 0; i < list.length; i++) {
      var img = list[i];
      var cn = (img.className && String(img.className)) || "";
      if (!desktop && cn.indexOf("md:block") !== -1) desktop = img;
      else if (!mobile && cn.indexOf("md:hidden") !== -1) mobile = img;
    }

    if (!desktop && list[0]) desktop = list[0];
    if (!mobile && list[1]) mobile = list[1];
    if (!mobile) mobile = desktop;
    if (!desktop) desktop = mobile;

    return {
      desktopSrc: desktop ? desktop.getAttribute("src") || desktop.src || "" : "",
      mobileSrc: mobile ? mobile.getAttribute("src") || mobile.src || "" : "",
      alt:
        (desktop && (desktop.getAttribute("alt") || "")) ||
        (mobile && (mobile.getAttribute("alt") || "")) ||
        "ad",
    };
  }

  function extractSlides(root) {
    var slides = [];
    var candidates = [];

    var marked = root.querySelectorAll(":scope > [data-cas-slide]");
    if (marked && marked.length) {
      candidates = Array.prototype.slice.call(marked);
    } else {
      candidates = Array.prototype.filter.call(root.children, function (el) {
        return el.nodeType === 1 && el.querySelector && el.querySelector("img");
      });
      if (!candidates.length) {
        candidates = Array.prototype.filter.call(root.children, function (el) {
          return el.nodeType === 1;
        });
      }
    }

    for (var i = 0; i < candidates.length; i++) {
      var node = candidates[i];
      var imgs = node.querySelectorAll("img");
      if (!imgs || !imgs.length) continue;

      var media = pickDesktopMobile(imgs);
      if (!media.desktopSrc && !media.mobileSrc) continue;

      var a = node.querySelector("a");
      var href = a ? a.getAttribute("href") || "" : "";
      var target = a ? a.getAttribute("target") || "" : "";
      var rel = a ? a.getAttribute("rel") || "" : "";
      var title = (a && (a.getAttribute("title") || "")) || media.alt || "";

      var prevent =
        truthyFlag(node.getAttribute("data-prevent-right-click")) ||
        truthyFlag(node.dataset && node.dataset.preventRightClick) ||
        Array.prototype.some.call(imgs, function (img) {
          return (
            truthyFlag(img.getAttribute("data-prevent-right-click")) ||
            truthyFlag(img.dataset && img.dataset.preventRightClick) ||
            truthyFlag(img.getAttribute("preventrightclick"))
          );
        });

      slides.push({
        id: null,
        desktopSrc: media.desktopSrc,
        mobileSrc: media.mobileSrc || media.desktopSrc,
        href: href,
        target: target,
        rel: rel,
        title: title,
        bg_color: "",
        preventRightClick: !!prevent,
      });
    }

    return slides;
  }

  function slideSignature(slides) {
    return slides
      .map(function (s) {
        return (
          (s.id != null ? String(s.id) : "") +
          "|" +
          (s.desktopSrc || "") +
          "|" +
          (s.mobileSrc || "")
        );
      })
      .join("||");
  }

  function hostIdFor(root) {
    return (root.id || "cas") + "-host";
  }

  function findExistingHost(root) {
    var next = root.nextElementSibling;
    if (next && next.getAttribute("data-cas-hero-host") === "1") return next;
    var byId = document.getElementById(hostIdFor(root));
    if (byId && byId.getAttribute("data-cas-hero-host") === "1") return byId;
    return null;
  }

  function chevronSvg(dir) {
    var points = dir === "left" ? "15 18 9 12 15 6" : "9 18 15 12 9 6";
    return (
      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:1.25rem;height:1.25rem" aria-hidden="true"><polyline points="' +
      points +
      '"/></svg>'
    );
  }

  function applyAspect(el) {
    try {
      var md = window.matchMedia && window.matchMedia(MD_MQ).matches;
      el.style.aspectRatio = md ? "3 / 1" : "2 / 1";
    } catch (e) {
      el.style.aspectRatio = "2 / 1";
    }
  }

  function applyImgVisibility(desktopImg, mobileImg) {
    var md = false;
    try {
      md = !!(window.matchMedia && window.matchMedia(MD_MQ).matches);
    } catch (e) {}
    if (desktopImg) desktopImg.style.display = md ? "block" : "none";
    if (mobileImg) mobileImg.style.display = md ? "none" : "block";
  }

  function styleFillImg(img) {
    img.style.position = "absolute";
    img.style.inset = "0";
    img.style.width = "100%";
    img.style.height = "100%";
    img.style.objectFit = "cover";
    img.style.margin = "0";
    img.draggable = false;
  }

  function destroyHost(host) {
    if (!host) return;
    if (host.__casHeroClear) {
      try {
        host.__casHeroClear();
      } catch (e) {}
    }
    if (host.parentNode) host.parentNode.removeChild(host);
  }

  function makeControlBtn(label) {
    var b = document.createElement("button");
    b.type = "button";
    b.setAttribute("aria-label", label);
    b.style.cursor = "pointer";
    b.style.border = "0";
    return b;
  }

  function buildSlideBody(slide) {
    var mediaWrap = document.createElement("div");
    mediaWrap.style.position = "relative";
    mediaWrap.style.width = "100%";
    mediaWrap.style.height = "100%";

    var desktopImg = document.createElement("img");
    desktopImg.src = slide.desktopSrc || slide.mobileSrc;
    desktopImg.alt = slide.title || "ad";
    styleFillImg(desktopImg);

    var mobileImg = document.createElement("img");
    mobileImg.src = slide.mobileSrc || slide.desktopSrc;
    mobileImg.alt = slide.title || "ad";
    styleFillImg(mobileImg);

    applyImgVisibility(desktopImg, mobileImg);
    mediaWrap.appendChild(desktopImg);
    mediaWrap.appendChild(mobileImg);

    var link = (slide.href || "").trim();
    var body;

    if (link) {
      if (isExternal(link)) {
        var a = document.createElement("a");
        a.href = link;
        a.title = slide.title || "";
        a.style.position = "absolute";
        a.style.inset = "0";
        a.style.display = "block";
        a.style.width = "100%";
        a.style.height = "100%";
        if (slide.target && slide.target !== "_self") {
          a.target = slide.target;
          a.rel = slide.rel || "noopener noreferrer";
        } else if (!slide.target) {
          // default new tab for external when unspecified
          a.target = "_blank";
          a.rel = "noopener noreferrer";
        }
        a.appendChild(mediaWrap);
        body = a;
      } else {
        var btn = document.createElement("button");
        btn.type = "button";
        btn.title = slide.title || "";
        btn.setAttribute("aria-label", slide.title || "ad");
        btn.style.position = "absolute";
        btn.style.inset = "0";
        btn.style.display = "block";
        btn.style.width = "100%";
        btn.style.height = "100%";
        btn.style.padding = "0";
        btn.style.margin = "0";
        btn.style.border = "0";
        btn.style.background = "transparent";
        btn.style.cursor = "pointer";
        btn.style.textAlign = "left";
        btn.appendChild(mediaWrap);
        btn.addEventListener("click", function (e) {
          e.preventDefault();
          navigate(link);
        });
        body = btn;
      }
    } else {
      var div = document.createElement("div");
      div.style.position = "absolute";
      div.style.inset = "0";
      div.appendChild(mediaWrap);
      body = div;
    }

    return { body: body, desktopImg: desktopImg, mobileImg: mobileImg };
  }

  function buildHost(root, slides) {
    destroyHost(findExistingHost(root));

    var host = document.createElement("div");
    host.id = hostIdFor(root);
    host.setAttribute("data-cas-hero-host", "1");
    host.setAttribute("data-cas-hero-sig", slideSignature(slides));
    host.setAttribute("role", "region");
    host.setAttribute("aria-roledescription", "carousel");
    host.setAttribute("aria-label", (slides[0] && slides[0].title) || "ads");
    host.className = "cas-hero-host relative w-full overflow-hidden rounded-xl";
    host.style.position = "relative";
    host.style.width = "100%";
    host.style.overflow = "hidden";
    host.style.borderRadius = "0.75rem";
    if (slides[0] && slides[0].bg_color) {
      host.style.backgroundColor = slides[0].bg_color;
    }

    var rootCn = (root.className && String(root.className)) || "";
    if (rootCn.indexOf("mb-4") !== -1) host.style.marginBottom = "1rem";
    else if (rootCn.indexOf("mb-2") !== -1) host.style.marginBottom = "0.5rem";

    var frame = document.createElement("div");
    frame.style.position = "relative";
    frame.style.width = "100%";
    applyAspect(frame);
    host.appendChild(frame);

    var state = {
      index: 0,
      paused: false,
      timer: null,
      slides: slides,
      dots: null,
      slideEls: [],
    };

    slides.forEach(function (slide, i) {
      var layer = document.createElement("div");
      layer.style.position = "absolute";
      layer.style.inset = "0";
      layer.style.width = "100%";
      layer.style.height = "100%";
      if (slide.bg_color) layer.style.backgroundColor = slide.bg_color;
      layer.setAttribute("data-cas-host-slide", String(i));

      var built = buildSlideBody(slide);
      layer.appendChild(built.body);
      layer.__casDesktop = built.desktopImg;
      layer.__casMobile = built.mobileImg;
      layer.__casPrevent = slide.preventRightClick;
      layer.__casBg = slide.bg_color || "";
      frame.appendChild(layer);
      state.slideEls.push(layer);
    });

    function show(index) {
      if (!slides.length) return;
      var n = ((index % slides.length) + slides.length) % slides.length;
      state.index = n;
      state.slideEls.forEach(function (el, i) {
        var on = i === n;
        el.style.opacity = on ? "1" : "0";
        el.style.pointerEvents = on ? "auto" : "none";
        el.style.zIndex = on ? "1" : "0";
        el.setAttribute("aria-hidden", on ? "false" : "true");
      });
      if (state.dots) {
        Array.prototype.forEach.call(state.dots, function (d, i) {
          var on = i === n;
          d.style.width = on ? "0.625rem" : "0.5rem";
          d.style.height = on ? "0.625rem" : "0.5rem";
          d.style.background = on ? "#fff" : "rgba(255,255,255,0.5)";
          if (on) d.setAttribute("aria-current", "true");
          else d.removeAttribute("aria-current");
        });
      }
      var cur = slides[n];
      host.setAttribute("aria-label", (cur && cur.title) || "ads");
      if (cur && cur.bg_color) host.style.backgroundColor = cur.bg_color;
      else host.style.backgroundColor = "";
    }

    state.go = function (next) {
      show(next);
    };

    if (slides.length > 1) {
      var prev = makeControlBtn("Previous");
      prev.style.position = "absolute";
      prev.style.left = "0.5rem";
      prev.style.top = "50%";
      prev.style.transform = "translateY(-50%)";
      prev.style.zIndex = "10";
      prev.style.width = "2.25rem";
      prev.style.height = "2.25rem";
      prev.style.borderRadius = "9999px";
      prev.style.background = "rgba(0,0,0,0.35)";
      prev.style.color = "#fff";
      prev.style.display = "flex";
      prev.style.alignItems = "center";
      prev.style.justifyContent = "center";
      prev.innerHTML = chevronSvg("left");
      prev.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        state.go(state.index - 1);
      });

      var next = makeControlBtn("Next");
      next.style.position = "absolute";
      next.style.right = "0.5rem";
      next.style.top = "50%";
      next.style.transform = "translateY(-50%)";
      next.style.zIndex = "10";
      next.style.width = "2.25rem";
      next.style.height = "2.25rem";
      next.style.borderRadius = "9999px";
      next.style.background = "rgba(0,0,0,0.35)";
      next.style.color = "#fff";
      next.style.display = "flex";
      next.style.alignItems = "center";
      next.style.justifyContent = "center";
      next.innerHTML = chevronSvg("right");
      next.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        state.go(state.index + 1);
      });

      var dots = document.createElement("div");
      dots.style.position = "absolute";
      dots.style.bottom = "0.5rem";
      dots.style.left = "0";
      dots.style.right = "0";
      dots.style.zIndex = "10";
      dots.style.display = "flex";
      dots.style.alignItems = "center";
      dots.style.justifyContent = "center";
      dots.style.gap = "0.375rem";

      slides.forEach(function (_s, i) {
        var d = makeControlBtn("Slide " + (i + 1));
        d.style.padding = "0";
        d.style.margin = "0";
        d.style.borderRadius = "9999px";
        d.style.background = "rgba(255,255,255,0.5)";
        d.style.width = "0.5rem";
        d.style.height = "0.5rem";
        d.addEventListener("click", function (e) {
          e.preventDefault();
          e.stopPropagation();
          state.go(i);
        });
        dots.appendChild(d);
      });
      state.dots = dots.children;

      host.appendChild(prev);
      host.appendChild(next);
      host.appendChild(dots);
    }

    function clearTimer() {
      if (state.timer) {
        clearInterval(state.timer);
        state.timer = null;
      }
    }
    function startTimer() {
      clearTimer();
      if (slides.length <= 1 || state.paused || INTERVAL_MS <= 0) return;
      state.timer = setInterval(function () {
        state.go(state.index + 1);
      }, INTERVAL_MS);
    }

    host.addEventListener("mouseenter", function () {
      state.paused = true;
      clearTimer();
    });
    host.addEventListener("mouseleave", function () {
      state.paused = false;
      startTimer();
    });

    var touchStartX = null;
    host.addEventListener(
      "touchstart",
      function (e) {
        touchStartX = e.touches && e.touches[0] ? e.touches[0].clientX : null;
      },
      { passive: true }
    );
    host.addEventListener(
      "touchend",
      function (e) {
        if (touchStartX == null) return;
        var endX =
          e.changedTouches && e.changedTouches[0]
            ? e.changedTouches[0].clientX
            : touchStartX;
        var dx = endX - touchStartX;
        touchStartX = null;
        if (Math.abs(dx) < SWIPE_MIN) return;
        if (dx > 0) state.go(state.index - 1);
        else state.go(state.index + 1);
      },
      { passive: true }
    );

    host.addEventListener(
      "contextmenu",
      function (e) {
        var t = e.target;
        if (!t || !t.closest) return;
        var img = t.closest("img");
        if (!img || !host.contains(img)) return;
        var layer = img.closest("[data-cas-host-slide]");
        if (layer && layer.__casPrevent) e.preventDefault();
      },
      true
    );

    try {
      var mql = window.matchMedia(MD_MQ);
      var onMq = function () {
        applyAspect(frame);
        state.slideEls.forEach(function (el) {
          applyImgVisibility(el.__casDesktop, el.__casMobile);
        });
      };
      if (mql.addEventListener) mql.addEventListener("change", onMq);
      else if (mql.addListener) mql.addListener(onMq);
      onMq();
    } catch (e) {}

    show(0);
    startTimer();

    host.__casHeroClear = clearTimer;
    host.__casHeroState = state;

    if (root.parentNode) {
      root.parentNode.insertBefore(host, root.nextSibling);
    }

    return host;
  }

  function applySlidesToMount(root, slides) {
    if (!slides || !slides.length) return;

    var sig = slideSignature(slides);
    var existing = findExistingHost(root);

    hideSourceRoot(root);

    if (existing && existing.getAttribute("data-cas-hero-sig") === sig) {
      return;
    }

    buildHost(root, slides);
    hideSourceRoot(root);
  }

  function enhanceMount(root) {
    if (!root || root.nodeType !== 1) return;
    if (root.getAttribute("data-cas-hero-host") === "1") return;
    if (root.classList.contains("cas-hero-host")) return;

    var slotKey = resolveSlotKey(root);

    // Always hide React/layout source every tick once we know it's a mount
    if (slotKey || root.classList.contains("cas-hero") || root.hasAttribute("data-cas-hero")) {
      // hide only after we have (or fail) slides — still hide empty mount stubs when API ok
    }

    if (slotKey) {
      var cached = slotCache[slotKey];
      if (cached && cached.status === "ok") {
        if (cached.items.length) {
          applySlidesToMount(root, cached.items);
        } else {
          // API returned empty — try DOM backup if any images exist
          var domEmpty = extractSlides(root);
          if (domEmpty.length) applySlidesToMount(root, domEmpty);
          else hideSourceRoot(root);
        }
        return;
      }
      if (cached && cached.status === "err") {
        var fallback = extractSlides(root);
        if (fallback.length) applySlidesToMount(root, fallback);
        return;
      }
      // pending or not started — kick fetch; hide mount to avoid flash of stub
      hideSourceRoot(root);
      fetchSlot(slotKey).then(function () {
        schedule();
      });
      return;
    }

    // No slot key: legacy DOM-only path
    var imgs = root.querySelectorAll("img");
    if (!imgs || !imgs.length) return;
    var slides = extractSlides(root);
    if (!slides.length) return;
    applySlidesToMount(root, slides);
  }

  function run() {
    try {
      var mounts = findMounts();
      for (var i = 0; i < mounts.length; i++) {
        enhanceMount(mounts[i]);
      }
      var sources = document.querySelectorAll(".cas-hero-source");
      for (var s = 0; s < sources.length; s++) {
        hideSourceRoot(sources[s]);
      }
    } catch (e) {}
  }

  var scheduled = null;
  function schedule() {
    if (scheduled) return;
    scheduled = requestAnimationFrame
      ? requestAnimationFrame(function () {
          scheduled = null;
          run();
        })
      : setTimeout(function () {
          scheduled = null;
          run();
        }, 80);
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", schedule);
  else schedule();
  setInterval(run, 1000);
  try {
    new MutationObserver(schedule).observe(document.documentElement, {
      childList: true,
      subtree: true,
    });
  } catch (e) {}
})();
