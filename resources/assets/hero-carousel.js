/*! custom-ad_slots — Bunjang-style hero carousel for [data-cas-hero] */
(function () {
  if (window.__casHeroCarouselInstalled) return;
  window.__casHeroCarouselInstalled = true;

  var INTERVAL_MS = 4000;
  var SWIPE_MIN = 40;

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

  function getSlides(root) {
    var marked = root.querySelectorAll(":scope > [data-cas-slide]");
    if (marked && marked.length) return Array.prototype.slice.call(marked);
    return Array.prototype.filter.call(root.children, function (el) {
      return el.nodeType === 1 && !el.hasAttribute("data-cas-hero-ui");
    });
  }

  function ensureViewport(root) {
    var vp = root.querySelector(":scope > [data-cas-hero-viewport]");
    if (vp) return vp;
    vp = document.createElement("div");
    vp.setAttribute("data-cas-hero-viewport", "1");
    vp.setAttribute("data-cas-hero-ui", "1");
    vp.className = "relative w-full aspect-[2/1] md:aspect-[3/1]";
    vp.style.position = "relative";
    vp.style.width = "100%";
    // aspect-ratio fallbacks when Tailwind classes are not applied to injected nodes
    vp.style.aspectRatio = "2 / 1";
    try {
      if (window.matchMedia && window.matchMedia("(min-width: 768px)").matches) {
        vp.style.aspectRatio = "3 / 1";
      }
    } catch (e) {}
    root.insertBefore(vp, root.firstChild);
    return vp;
  }

  function moveSlidesIntoViewport(root, vp, slides) {
    slides.forEach(function (slide) {
      if (slide.parentElement !== vp) vp.appendChild(slide);
      slide.style.position = "absolute";
      slide.style.inset = "0";
      slide.style.width = "100%";
      slide.style.height = "100%";
      slide.style.margin = "0";
      if (!slide.getAttribute("data-cas-slide")) slide.setAttribute("data-cas-slide", "1");
    });
  }

  function showSlide(slides, index) {
    slides.forEach(function (slide, i) {
      var on = i === index;
      slide.style.opacity = on ? "1" : "0";
      slide.style.pointerEvents = on ? "auto" : "none";
      slide.style.zIndex = on ? "1" : "0";
      slide.setAttribute("aria-hidden", on ? "false" : "true");
    });
  }

  function bindPreventRightClick(root) {
    if (root.getAttribute("data-cas-hero-ctx") === "1") return;
    root.setAttribute("data-cas-hero-ctx", "1");
    root.addEventListener(
      "contextmenu",
      function (e) {
        var t = e.target;
        if (!t || !t.closest) return;
        var img = t.closest("img");
        if (!img || !root.contains(img)) return;
        var slide = img.closest("[data-cas-slide]");
        var flag =
          img.getAttribute("data-prevent-right-click") ||
          (slide && slide.getAttribute("data-prevent-right-click")) ||
          img.getAttribute("preventrightclick") ||
          "";
        // Also honor G7 Img preventRightClick when reflected as boolean attr / dataset
        if (
          flag === "1" ||
          flag === "true" ||
          img.dataset.preventRightClick === "true" ||
          img.dataset.preventRightClick === "1" ||
          (slide &&
            (slide.dataset.preventRightClick === "true" ||
              slide.dataset.preventRightClick === "1"))
        ) {
          e.preventDefault();
        }
      },
      true
    );
  }

  function bindRelativeNav(root) {
    if (root.getAttribute("data-cas-hero-nav") === "1") return;
    root.setAttribute("data-cas-hero-nav", "1");
    root.addEventListener(
      "click",
      function (e) {
        var t = e.target;
        if (!t || !t.closest) return;
        var a = t.closest("a");
        if (!a || !root.contains(a)) return;
        // Let modified clicks / new-tab targets alone
        if (e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        if (a.target && a.target !== "" && a.target !== "_self") return;
        var href = a.getAttribute("href");
        if (!href || href === "#" || href.indexOf("javascript:") === 0) return;
        if (isExternal(href)) return;
        e.preventDefault();
        navigate(href);
      },
      true
    );
  }

  function makeBtn(label, className) {
    var b = document.createElement("button");
    b.type = "button";
    b.setAttribute("data-cas-hero-ui", "1");
    b.setAttribute("aria-label", label);
    b.className = className;
    b.style.cursor = "pointer";
    return b;
  }

  function chevronSvg(dir) {
    var points = dir === "left" ? "15 18 9 12 15 6" : "9 18 15 12 9 6";
    return (
      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:1.25rem;height:1.25rem" aria-hidden="true"><polyline points="' +
      points +
      '"/></svg>'
    );
  }

  function ensureControls(root, slides, state) {
    var existing = root.querySelector(":scope > [data-cas-hero-controls]");
    if (slides.length <= 1) {
      if (existing) existing.remove();
      return null;
    }
    if (existing) return existing;

    var wrap = document.createElement("div");
    wrap.setAttribute("data-cas-hero-controls", "1");
    wrap.setAttribute("data-cas-hero-ui", "1");

    var prev = makeBtn(
      "Previous",
      "absolute left-2 top-1/2 -translate-y-1/2 z-10 flex items-center justify-center w-9 h-9 rounded-full bg-black/35 text-white hover:bg-black/50 border-0"
    );
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
    prev.style.border = "0";
    prev.style.display = "flex";
    prev.style.alignItems = "center";
    prev.style.justifyContent = "center";
    prev.innerHTML = chevronSvg("left");
    prev.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      state.go(state.index - 1);
    });

    var next = makeBtn(
      "Next",
      "absolute right-2 top-1/2 -translate-y-1/2 z-10 flex items-center justify-center w-9 h-9 rounded-full bg-black/35 text-white hover:bg-black/50 border-0"
    );
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
    next.style.border = "0";
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
    dots.setAttribute("data-cas-hero-dots", "1");
    dots.setAttribute("data-cas-hero-ui", "1");
    dots.className = "absolute bottom-2 left-0 right-0 z-10 flex items-center justify-center gap-1.5";
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
      var d = makeBtn("Slide " + (i + 1), "p-0 m-0 border-0 rounded-full");
      d.style.padding = "0";
      d.style.margin = "0";
      d.style.border = "0";
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

    wrap.appendChild(prev);
    wrap.appendChild(next);
    wrap.appendChild(dots);
    root.appendChild(wrap);

    state.dots = dots.children;
    return wrap;
  }

  function updateDots(state) {
    if (!state.dots) return;
    Array.prototype.forEach.call(state.dots, function (d, i) {
      var on = i === state.index;
      d.style.width = on ? "0.625rem" : "0.5rem";
      d.style.height = on ? "0.625rem" : "0.5rem";
      d.style.background = on ? "#fff" : "rgba(255,255,255,0.5)";
      if (on) d.setAttribute("aria-current", "true");
      else d.removeAttribute("aria-current");
    });
  }

  function enhance(root) {
    if (!root || root.nodeType !== 1) return;
    var slides = getSlides(root);
    // Filter: only keep slides that look like image banners (have an img), matching feat static+image
    slides = slides.filter(function (s) {
      return s.querySelector("img");
    });
    if (slides.length === 0) {
      // leave stacked text/dynamic alone; mark ready so we don't loop forever
      root.setAttribute("data-cas-hero-ready", "1");
      return;
    }

    if (!root.style.position) root.style.position = "relative";
    root.style.overflow = "hidden";

    var vp = ensureViewport(root, slides);
    moveSlidesIntoViewport(root, vp, slides);

    // Hide non-image direct children (text/dynamic leftovers) from carousel flow
    Array.prototype.forEach.call(root.children, function (el) {
      if (el.hasAttribute("data-cas-hero-ui") || el.hasAttribute("data-cas-hero-viewport")) return;
      if (slides.indexOf(el) === -1 && !el.querySelector || (el.querySelector && !el.querySelector("img") && !el.hasAttribute("data-cas-slide"))) {
        // leftover iteration nodes without images — keep but don't participate
        if (slides.indexOf(el) === -1 && !el.hasAttribute("data-cas-hero-viewport") && !el.hasAttribute("data-cas-hero-controls")) {
          if (!el.querySelector("img")) {
            /* leave visible below carousel if any — but typically filtered by layout if */
          }
        }
      }
    });

    var state = {
      index: 0,
      paused: false,
      timer: null,
      slides: slides,
      dots: null,
      go: function (next) {
        if (!slides.length) return;
        var n = ((next % slides.length) + slides.length) % slides.length;
        state.index = n;
        showSlide(slides, n);
        updateDots(state);
      },
    };

    // Restore index if already enhanced
    var prevIdx = parseInt(root.getAttribute("data-cas-hero-index") || "0", 10);
    if (!isNaN(prevIdx) && prevIdx >= 0 && prevIdx < slides.length) state.index = prevIdx;

    showSlide(slides, state.index);
    ensureControls(root, slides, state);
    updateDots(state);

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
        root.setAttribute("data-cas-hero-index", String(state.index));
      }, INTERVAL_MS);
    }

    if (root.getAttribute("data-cas-hero-ready") !== "1") {
      root.addEventListener("mouseenter", function () {
        state.paused = true;
        clearTimer();
      });
      root.addEventListener("mouseleave", function () {
        state.paused = false;
        startTimer();
      });

      var touchStartX = null;
      root.addEventListener(
        "touchstart",
        function (e) {
          touchStartX = e.touches && e.touches[0] ? e.touches[0].clientX : null;
        },
        { passive: true }
      );
      root.addEventListener(
        "touchend",
        function (e) {
          if (touchStartX == null) return;
          var endX = e.changedTouches && e.changedTouches[0] ? e.changedTouches[0].clientX : touchStartX;
          var dx = endX - touchStartX;
          touchStartX = null;
          if (Math.abs(dx) < SWIPE_MIN) return;
          if (dx > 0) state.go(state.index - 1);
          else state.go(state.index + 1);
          root.setAttribute("data-cas-hero-index", String(state.index));
        },
        { passive: true }
      );

      // Responsive aspect-ratio on resize
      try {
        var mql = window.matchMedia("(min-width: 768px)");
        var applyAspect = function () {
          vp.style.aspectRatio = mql.matches ? "3 / 1" : "2 / 1";
        };
        if (mql.addEventListener) mql.addEventListener("change", applyAspect);
        else if (mql.addListener) mql.addListener(applyAspect);
        applyAspect();
      } catch (e) {}
    }

    bindPreventRightClick(root);
    bindRelativeNav(root);

    // Fill images inside slides
    slides.forEach(function (slide) {
      var media = slide.querySelectorAll("img, a, div");
      Array.prototype.forEach.call(slide.querySelectorAll("a, div"), function (el) {
        if (el.parentElement === slide || el === slide.firstElementChild) {
          el.style.display = el.style.display || "";
          if (el.tagName === "A" || (el.tagName === "DIV" && el.querySelector("img"))) {
            el.style.position = "absolute";
            el.style.inset = "0";
            el.style.width = "100%";
            el.style.height = "100%";
            el.style.display = "block";
          }
        }
      });
      Array.prototype.forEach.call(slide.querySelectorAll("img"), function (img) {
        img.style.objectFit = "cover";
        img.style.width = "100%";
        img.style.height = "100%";
        // keep Tailwind hidden/block md: classes; absolute fill when visible
        img.style.position = "absolute";
        img.style.inset = "0";
        img.draggable = false;
      });
    });

    root.setAttribute("data-cas-hero-ready", "1");
    root.setAttribute("data-cas-hero-index", String(state.index));
    startTimer();

    // stash for re-run cleanup of old timer
    if (root.__casHeroClear) root.__casHeroClear();
    root.__casHeroClear = clearTimer;
  }

  function run() {
    try {
      var roots = document.querySelectorAll("[data-cas-hero]");
      for (var i = 0; i < roots.length; i++) {
        var root = roots[i];
        // Re-enhance when slide count changes (SPA data load)
        var slides = getSlides(root);
        var imgSlides = slides.filter(function (s) {
          return s.querySelector("img");
        });
        var ready = root.getAttribute("data-cas-hero-ready") === "1";
        var prevCount = parseInt(root.getAttribute("data-cas-hero-count") || "-1", 10);
        if (!ready || prevCount !== imgSlides.length) {
          if (root.__casHeroClear) root.__casHeroClear();
          // Remove old controls/viewport markers if slide set changed
          if (ready && prevCount !== imgSlides.length) {
            root.removeAttribute("data-cas-hero-ready");
            var oldControls = root.querySelectorAll("[data-cas-hero-controls]");
            for (var c = 0; c < oldControls.length; c++) oldControls[c].remove();
          }
          root.setAttribute("data-cas-hero-count", String(imgSlides.length));
          enhance(root);
        }
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
    new MutationObserver(schedule).observe(document.documentElement, { childList: true, subtree: true });
  } catch (e) {}
})();
