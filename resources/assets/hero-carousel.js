/*! custom-ad_slots — API mounts (carousel + stacks) + path-routed home/global; native stacks for non-home */
(function () {
  if (window.__casAdRenderInstalled) return;
  window.__casAdRenderInstalled = true;

  var INTERVAL_MS = 4000;
  var SWIPE_MIN = 40;
  var MD_MQ = "(min-width: 768px)";
  var PLACEMENTS_URL = "/api/modules/custom-ad_slots/placements";
  var CAROUSEL_SLOTS = { "home.top": true, "global.top": true };

  /** @type {Object.<string, {status:string, items:Array, promise:Promise|null, error:*} >} */
  var slotCache = {};
  var mountTimers = {};
  /** last path-routed page slot pair signature */
  var lastPageSig = "";

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

  function normalizePathname(pathname) {
    var p = String(pathname || "/");
    try {
      p = decodeURIComponent(p);
    } catch (e) {}
    p = p.split("?")[0].split("#")[0];
    // strip trailing slash except root
    if (p.length > 1 && p.charAt(p.length - 1) === "/") p = p.slice(0, -1);
    if (!p) p = "/";
    return p;
  }

  /**
   * Map current URL → page top/bottom slot keys (null = no page ads).
   * Liberal heuristics for gnuboard G7 sirsoft-basic URLs.
   * @returns {{ top: ?string, bottom: ?string, excluded: boolean, path: string }}
   */
  function readShopBase() {
    try {
      var st = window.G7Core && window.G7Core.state;
      if (st && typeof st.shopBase === "string" && st.shopBase) {
        return normalizePathname(st.shopBase);
      }
      if (st && st._global && typeof st._global.shopBase === "string" && st._global.shopBase) {
        return normalizePathname(st._global.shopBase);
      }
    } catch (e) {}
    try {
      var g = window._global;
      if (g && typeof g.shopBase === "string" && g.shopBase) {
        return normalizePathname(g.shopBase);
      }
    } catch (e2) {}
    return "";
  }

  /** Strip configured shopBase prefix for shop URL matching only. */
  function pathWithoutShopBase(pathname, shopBase) {
    if (!shopBase || shopBase === "/") return pathname;
    var p = String(pathname || "/");
    var sb = String(shopBase);
    var pl = p.toLowerCase();
    var sbl = sb.toLowerCase();
    if (pl === sbl) return "/";
    if (pl.indexOf(sbl + "/") === 0) {
      var rest = p.slice(sb.length);
      return rest && rest.charAt(0) === "/" ? rest : "/" + rest;
    }
    return pathname;
  }

  function resolvePageSlots() {
    var path = normalizePathname(location.pathname || "/");
    var lower = path.toLowerCase();

    // Optional G7 route hint if available
    try {
      var g7 =
        (window.G7Core && (window.G7Core.route || window.G7Core.currentRoute)) ||
        (window.__G7_ROUTE__ ) ||
        null;
      if (typeof g7 === "string" && g7) {
        // layout-like names: shop/index, board/show, mypage/profile
        var n = g7.toLowerCase().replace(/^\/+/, "");
        if (
          n === "shop/checkout" ||
          n === "checkout" ||
          n.indexOf("checkout") >= 0 ||
          n === "order_complete" ||
          n.indexOf("guest_order") >= 0
        ) {
          return { top: null, bottom: null, excluded: true, path: path };
        }
        if (n === "home" || n === "home/index") {
          return { top: "home.top", bottom: "home.bottom", excluded: false, path: path };
        }
        if (n === "shop/cart") {
          return { top: "shop.cart.top", bottom: "shop.cart.bottom", excluded: false, path: path };
        }
        if (n === "shop/index" || n === "shop/category") {
          return { top: "shop.list.top", bottom: "shop.list.bottom", excluded: false, path: path };
        }
        if (n === "shop/show") {
          return { top: "shop.detail.top", bottom: "shop.detail.bottom", excluded: false, path: path };
        }
        if (n === "board/popular") {
          return { top: "board.popular.top", bottom: "board.popular.bottom", excluded: false, path: path };
        }
        if (n === "board/boards") {
          return { top: "board.boards.top", bottom: "board.boards.bottom", excluded: false, path: path };
        }
        if (n === "board/form") {
          return { top: "board.form.top", bottom: "board.form.bottom", excluded: false, path: path };
        }
        if (n === "board/show") {
          return { top: "board.show.top", bottom: "board.show.bottom", excluded: false, path: path };
        }
        if (n === "board/index") {
          return { top: "board.index.top", bottom: "board.index.bottom", excluded: false, path: path };
        }
        if (n === "mypage" || n.indexOf("mypage/") === 0) {
          return { top: "mypage.top", bottom: "mypage.bottom", excluded: false, path: path };
        }
      }
    } catch (e) {}

    // --- Pathname heuristics (liberal) ---

    // Checkout / order complete / guest order — NO page mounts
    if (
      /\/checkout(\/|$)/i.test(lower) ||
      /order[_-]?complete/i.test(lower) ||
      /guest[_-]?order/i.test(lower) ||
      /\/orders\/[^/]+\/complete(\/|$)/i.test(lower) ||
      /\/guest\/orders(\/|$)/i.test(lower)
    ) {
      return { top: null, bottom: null, excluded: true, path: path };
    }

    // Home — keep on original path (do not apply shopBase strip here)
    if (lower === "/" || lower === "/home" || lower === "/index" || lower === "/main") {
      return { top: "home.top", bottom: "home.bottom", excluded: false, path: path };
    }

    // Shop URL matching may need shopBase strip (secondary hardening)
    var shopBase = readShopBase();
    var shopPath = pathWithoutShopBase(path, shopBase);
    var shopLower = shopPath.toLowerCase();

    // Mypage (all)
    if (lower === "/mypage" || lower.indexOf("/mypage/") === 0) {
      return { top: "mypage.top", bottom: "mypage.bottom", excluded: false, path: path };
    }

    // Board popular: /boards/popular or /board/popular
    if (
      lower === "/boards/popular" ||
      lower === "/board/popular" ||
      lower.indexOf("/boards/popular/") === 0 ||
      lower.indexOf("/board/popular/") === 0
    ) {
      return { top: "board.popular.top", bottom: "board.popular.bottom", excluded: false, path: path };
    }

    // Board boards list: /boards or /board/boards
    if (
      lower === "/boards" ||
      lower === "/board/boards" ||
      lower.indexOf("/board/boards/") === 0
    ) {
      return { top: "board.boards.top", bottom: "board.boards.bottom", excluded: false, path: path };
    }

    // Board form: write / edit / form
    if (
      /\/board\/[^/]+\/(write|edit)(\/|$)/i.test(lower) ||
      lower === "/board/form" ||
      lower.indexOf("/board/form/") === 0 ||
      /\/boards?\/form(\/|$)/i.test(lower)
    ) {
      return { top: "board.form.top", bottom: "board.form.bottom", excluded: false, path: path };
    }

    // Board show: /board/{slug}/{id}
    if (/^\/board\/[^/]+\/[^/]+(\/|$)/i.test(lower)) {
      return { top: "board.show.top", bottom: "board.show.bottom", excluded: false, path: path };
    }

    // Board index: /board/{slug}
    if (/^\/board\/[^/]+$/i.test(lower)) {
      return { top: "board.index.top", bottom: "board.index.bottom", excluded: false, path: path };
    }

    // Shop cart (match shopLower so /{shopBase}/cart works)
    if (
      /\/shop\/cart(\/|$)/i.test(shopLower) ||
      shopLower === "/cart" ||
      shopLower.indexOf("/cart/") === 0
    ) {
      // avoid mypage already handled; plain /cart under shopBase '' edge case
      if (lower.indexOf("/mypage") !== 0) {
        return { top: "shop.cart.top", bottom: "shop.cart.bottom", excluded: false, path: path };
      }
    }

    // Shop list: /shop, /shop/products, /shop/category/*, /products (no_route)
    if (
      shopLower === "/shop" ||
      shopLower === "/shop/products" ||
      shopLower.indexOf("/shop/products?") === 0 ||
      /^\/shop\/category(\/|$)/i.test(shopLower) ||
      shopLower === "/products" ||
      /^\/category(\/|$)/i.test(shopLower)
    ) {
      return { top: "shop.list.top", bottom: "shop.list.bottom", excluded: false, path: path };
    }

    // Shop detail: /shop/products/{code}, /products/{code} — not cart/checkout
    if (
      /^\/shop\/products\/[^/]+/i.test(shopLower) ||
      /^\/products\/[^/]+/i.test(shopLower) ||
      /^\/shop\/[^/]+$/i.test(shopLower)
    ) {
      // /shop/cart already handled; /shop/checkout excluded
      if (!/^\/shop\/(cart|checkout|orders|guest|category|products)$/i.test(shopLower)) {
        return { top: "shop.detail.top", bottom: "shop.detail.bottom", excluded: false, path: path };
      }
    }

    // Fallback: /shop/* remaining → detail-ish (liberal)
    if (shopLower.indexOf("/shop/") === 0) {
      return { top: "shop.detail.top", bottom: "shop.detail.bottom", excluded: false, path: path };
    }

    return { top: null, bottom: null, excluded: false, path: path };
  }

  function resolveSlotKey(el) {
    if (!el || el.nodeType !== 1) return null;
    var attr =
      el.getAttribute("data-cas-ad-slot") ||
      el.getAttribute("data-cas-hero-slot") ||
      (el.dataset && (el.dataset.casAdSlot || el.dataset.casHeroSlot)) ||
      "";
    if (attr) return String(attr).trim();
    var id = el.id || "";
    var map = {
      ad_home_top_wrap: "home.top",
      ad_home_mid_wrap: "home.mid",
      ad_home_bottom_wrap: "home.bottom",
      ad_global_top_hero: "global.top",
      ad_global_top_wrap: "global.top",
      ad_global_bottom_stack: "global.bottom",
      ad_global_bottom_wrap: "global.bottom",
      ad_shop_list_top_wrap: "shop.list.top",
      ad_shop_list_bottom_wrap: "shop.list.bottom",
      ad_shop_detail_top_wrap: "shop.detail.top",
      ad_shop_detail_bottom_wrap: "shop.detail.bottom",
      ad_shop_cart_top_wrap: "shop.cart.top",
      ad_shop_cart_bottom_wrap: "shop.cart.bottom",
      ad_board_popular_top_wrap: "board.popular.top",
      ad_board_popular_bottom_wrap: "board.popular.bottom",
      ad_board_index_top_wrap: "board.index.top",
      ad_board_index_bottom_wrap: "board.index.bottom",
      ad_board_show_top_wrap: "board.show.top",
      ad_board_show_bottom_wrap: "board.show.bottom",
      ad_board_form_top_wrap: "board.form.top",
      ad_board_form_bottom_wrap: "board.form.bottom",
      ad_board_boards_top_wrap: "board.boards.top",
      ad_board_boards_bottom_wrap: "board.boards.bottom",
      ad_mypage_top_wrap: "mypage.top",
      ad_mypage_bottom_wrap: "mypage.bottom",
    };
    return map[id] || null;
  }

  function findMounts() {
    var found = [];
    function add(el) {
      if (!el || el.nodeType !== 1) return;
      if (el.getAttribute("data-cas-ad-host") === "1") return;
      // Skip path-routed role mounts — handled separately
      var role = el.getAttribute("data-cas-ad-role") || "";
      if (role === "page-top" || role === "page-bottom") return;
      for (var i = 0; i < found.length; i++) {
        if (found[i] === el) return;
      }
      found.push(el);
    }

    var byAttr = document.querySelectorAll("[data-cas-ad-slot], [data-cas-hero-slot]");
    for (var a = 0; a < byAttr.length; a++) add(byAttr[a]);

    var ids = [
      "ad_home_top_wrap",
      "ad_home_mid_wrap",
      "ad_home_bottom_wrap",
      "ad_global_top_hero",
      "ad_global_bottom_stack",
      "ad_shop_list_top_wrap",
      "ad_shop_list_bottom_wrap",
      "ad_shop_detail_top_wrap",
      "ad_shop_detail_bottom_wrap",
      "ad_shop_cart_top_wrap",
      "ad_shop_cart_bottom_wrap",
      "ad_board_popular_top_wrap",
      "ad_board_popular_bottom_wrap",
      "ad_board_index_top_wrap",
      "ad_board_index_bottom_wrap",
      "ad_board_show_top_wrap",
      "ad_board_show_bottom_wrap",
      "ad_board_form_top_wrap",
      "ad_board_form_bottom_wrap",
      "ad_board_boards_top_wrap",
      "ad_board_boards_bottom_wrap",
      "ad_mypage_top_wrap",
      "ad_mypage_bottom_wrap",
    ];
    for (var i = 0; i < ids.length; i++) {
      var el = document.getElementById(ids[i]);
      // Native stacks (v1.2.9) reuse wrap ids but have no data-cas-ad-slot —
      // do not claim them or JS would wipe layout-rendered banners.
      if (
        el &&
        (el.getAttribute("data-cas-ad-slot") || el.getAttribute("data-cas-hero-slot"))
      ) {
        add(el);
      }
    }

    // Prefer innermost mount when both wrap + hero exist
    return found.filter(function (el) {
      for (var j = 0; j < found.length; j++) {
        if (found[j] !== el && el.contains(found[j])) return false;
      }
      return true;
    });
  }

  function findPageRoleMounts(role) {
    var out = [];
    var byId =
      role === "page-top"
        ? document.getElementById("cas_page_top_mount")
        : document.getElementById("cas_page_bottom_mount");
    if (byId) out.push(byId);
    var nodes = document.querySelectorAll('[data-cas-ad-role="' + role + '"]');
    for (var i = 0; i < nodes.length; i++) {
      var n = nodes[i];
      var dup = false;
      for (var j = 0; j < out.length; j++) {
        if (out[j] === n) {
          dup = true;
          break;
        }
      }
      if (!dup) out.push(n);
    }
    return out;
  }

  function normalizePlacementsPayload(json) {
    if (!json) return [];
    var data = json.data !== undefined ? json.data : json;
    if (Array.isArray(data)) return data;
    if (data && Array.isArray(data.data)) return data.data;
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

  /** Invalidate cache entry so SPA nav can refetch fresh items for a new slot */
  function invalidateSlot(slotKey) {
    if (slotKey && slotCache[slotKey]) delete slotCache[slotKey];
  }

  function slideSignature(slides) {
    return slides
      .map(function (s) {
        return (
          (s.id != null ? String(s.id) : "") +
          "|" +
          (s.desktopSrc || "") +
          "|" +
          (s.mobileSrc || "") +
          "|" +
          (s.href || "")
        );
      })
      .join("||");
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

  function styleStackImg(img) {
    img.style.display = "block";
    img.style.width = "100%";
    img.style.height = "auto";
    img.style.objectFit = "cover";
    img.style.borderRadius = "0.5rem";
    img.style.margin = "0";
    img.draggable = false;
  }

  function chevronSvg(dir) {
    var points = dir === "left" ? "15 18 9 12 15 6" : "9 18 15 12 9 6";
    return (
      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:1.25rem;height:1.25rem" aria-hidden="true"><polyline points="' +
      points +
      '"/></svg>'
    );
  }

  function makeControlBtn(label) {
    var b = document.createElement("button");
    b.type = "button";
    b.setAttribute("aria-label", label);
    b.style.cursor = "pointer";
    b.style.border = "0";
    return b;
  }

  function clearMountTimers(mount) {
    var key = mount.__casMountKey;
    if (key && mountTimers[key]) {
      try {
        mountTimers[key]();
      } catch (e) {}
      delete mountTimers[key];
    }
  }

  function buildLinkedMedia(slide, fillMode) {
    var mediaWrap = document.createElement("div");
    if (fillMode) {
      mediaWrap.style.position = "relative";
      mediaWrap.style.width = "100%";
      mediaWrap.style.height = "100%";
    } else {
      mediaWrap.style.position = "relative";
      mediaWrap.style.width = "100%";
    }

    var desktopImg = document.createElement("img");
    desktopImg.src = slide.desktopSrc || slide.mobileSrc;
    desktopImg.alt = slide.title || "ad";
    if (fillMode) styleFillImg(desktopImg);
    else styleStackImg(desktopImg);

    var mobileImg = document.createElement("img");
    mobileImg.src = slide.mobileSrc || slide.desktopSrc;
    mobileImg.alt = slide.title || "ad";
    if (fillMode) styleFillImg(mobileImg);
    else styleStackImg(mobileImg);

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
        if (fillMode) {
          a.style.position = "absolute";
          a.style.inset = "0";
          a.style.display = "block";
          a.style.width = "100%";
          a.style.height = "100%";
        } else {
          a.style.display = "block";
          a.style.width = "100%";
        }
        if (slide.target && slide.target !== "_self") {
          a.target = slide.target;
          a.rel = slide.rel || "noopener noreferrer";
        } else if (!slide.target) {
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
        if (fillMode) {
          btn.style.position = "absolute";
          btn.style.inset = "0";
          btn.style.height = "100%";
        }
        btn.style.display = "block";
        btn.style.width = "100%";
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
      if (fillMode) {
        div.style.position = "absolute";
        div.style.inset = "0";
      }
      div.style.width = "100%";
      div.appendChild(mediaWrap);
      body = div;
    }

    return { body: body, desktopImg: desktopImg, mobileImg: mobileImg };
  }

  function buildCarouselInto(mount, slides) {
    var host = document.createElement("div");
    host.setAttribute("data-cas-ad-host", "1");
    host.setAttribute("role", "region");
    host.setAttribute("aria-roledescription", "carousel");
    host.setAttribute("aria-label", (slides[0] && slides[0].title) || "ads");
    host.className = "cas-ad-carousel relative w-full overflow-hidden rounded-xl";
    host.style.position = "relative";
    host.style.width = "100%";
    host.style.overflow = "hidden";
    host.style.borderRadius = "0.75rem";
    if (slides[0] && slides[0].bg_color) {
      host.style.backgroundColor = slides[0].bg_color;
    }

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

      var built = buildLinkedMedia(slide, true);
      layer.appendChild(built.body);
      layer.__casDesktop = built.desktopImg;
      layer.__casMobile = built.mobileImg;
      layer.__casPrevent = slide.preventRightClick;
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
      prev.style.cssText =
        "position:absolute;left:0.5rem;top:50%;transform:translateY(-50%);z-index:10;width:2.25rem;height:2.25rem;border-radius:9999px;background:rgba(0,0,0,0.35);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;border:0";
      prev.innerHTML = chevronSvg("left");
      prev.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        state.go(state.index - 1);
      });

      var next = makeControlBtn("Next");
      next.style.cssText =
        "position:absolute;right:0.5rem;top:50%;transform:translateY(-50%);z-index:10;width:2.25rem;height:2.25rem;border-radius:9999px;background:rgba(0,0,0,0.35);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;border:0";
      next.innerHTML = chevronSvg("right");
      next.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        state.go(state.index + 1);
      });

      var dots = document.createElement("div");
      dots.style.cssText =
        "position:absolute;bottom:0.5rem;left:0;right:0;z-index:10;display:flex;align-items:center;justify-content:center;gap:0.375rem";

      slides.forEach(function (_s, i) {
        var d = makeControlBtn("Slide " + (i + 1));
        d.style.cssText =
          "padding:0;margin:0;border-radius:9999px;background:rgba(255,255,255,0.5);width:0.5rem;height:0.5rem;cursor:pointer;border:0";
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

    var key = (mount.id || "cas") + ":" + (mount.getAttribute("data-cas-ad-slot") || mount.getAttribute("data-cas-ad-role") || "");
    mount.__casMountKey = key;
    mountTimers[key] = clearTimer;

    mount.innerHTML = "";
    mount.appendChild(host);
    return host;
  }

  function buildStackInto(mount, slides) {
    var host = document.createElement("div");
    host.setAttribute("data-cas-ad-host", "1");
    host.className = "cas-ad-stack flex flex-col gap-3 w-full";
    host.style.display = "flex";
    host.style.flexDirection = "column";
    host.style.gap = "0.75rem";
    host.style.width = "100%";

    var imgPairs = [];

    slides.forEach(function (slide) {
      var row = document.createElement("div");
      row.className = "w-full overflow-hidden rounded-lg";
      row.style.width = "100%";
      row.style.overflow = "hidden";
      row.style.borderRadius = "0.5rem";
      if (slide.bg_color) row.style.backgroundColor = slide.bg_color;

      var built = buildLinkedMedia(slide, false);
      row.appendChild(built.body);
      row.__casPrevent = slide.preventRightClick;
      imgPairs.push(built);
      host.appendChild(row);
    });

    Array.prototype.forEach.call(host.children, function (row) {
      if (!row.__casPrevent) return;
      row.addEventListener(
        "contextmenu",
        function (e) {
          if (e.target && e.target.closest && e.target.closest("img")) {
            e.preventDefault();
          }
        },
        true
      );
    });

    try {
      var mql = window.matchMedia(MD_MQ);
      var onMq = function () {
        imgPairs.forEach(function (p) {
          applyImgVisibility(p.desktopImg, p.mobileImg);
        });
      };
      if (mql.addEventListener) mql.addEventListener("change", onMq);
      else if (mql.addListener) mql.addListener(onMq);
      onMq();
    } catch (e) {}

    clearMountTimers(mount);
    mount.innerHTML = "";
    mount.appendChild(host);
    return host;
  }

  function removeLegacySiblingHosts() {
    var hosts = document.querySelectorAll("[data-cas-hero-host='1']");
    for (var i = 0; i < hosts.length; i++) {
      var h = hosts[i];
      if (h.__casHeroClear) {
        try {
          h.__casHeroClear();
        } catch (e) {}
      }
      if (h.parentNode) h.parentNode.removeChild(h);
    }
  }

  function clearAndHideMount(mount) {
    if (!mount || mount.nodeType !== 1) return;
    clearMountTimers(mount);
    mount.setAttribute("data-cas-ad-sig", "__empty__");
    mount.setAttribute("data-cas-page-slot", "");
    mount.setAttribute("aria-hidden", "true");
    var host = mount.querySelector("[data-cas-ad-host='1']");
    if (host && host.parentNode === mount) {
      try {
        mount.removeChild(host);
      } catch (e) {}
    }
    // Also clear leftover children inside role mounts
    try {
      while (mount.firstChild) mount.removeChild(mount.firstChild);
    } catch (e2) {}
    mount.style.display = "none";
  }

  function hideEmptyMount(mount) {
    // Only the ad mount itself — never parents or page content.
    if (!mount || mount.nodeType !== 1) return;
    var role = mount.getAttribute("data-cas-ad-role") || "";
    if (role !== "page-top" && role !== "page-bottom" && !resolveSlotKey(mount)) return;
    clearAndHideMount(mount);
  }

  function applyToMount(mount, slotKey, slides) {
    if (!mount || mount.nodeType !== 1) return;
    var role = mount.getAttribute("data-cas-ad-role") || "";
    var isPageRole = role === "page-top" || role === "page-bottom";
    // Safety: only operate on recognized ad mounts
    if (!isPageRole) {
      if (!slotKey || resolveSlotKey(mount) !== slotKey) return;
    } else if (!slotKey) {
      clearAndHideMount(mount);
      return;
    }

    if (!slides || !slides.length) {
      hideEmptyMount(mount);
      return;
    }

    var sig = slotKey + "::" + slideSignature(slides);
    if (
      mount.getAttribute("data-cas-ad-sig") === sig &&
      mount.querySelector("[data-cas-ad-host='1']")
    ) {
      return;
    }

    clearMountTimers(mount);
    mount.setAttribute("data-cas-ad-sig", sig);
    mount.setAttribute("data-cas-page-slot", slotKey);
    mount.removeAttribute("aria-hidden");
    mount.style.display = "";

    if (CAROUSEL_SLOTS[slotKey]) {
      buildCarouselInto(mount, slides);
    } else {
      buildStackInto(mount, slides);
    }
  }

  function enhanceMount(mount) {
    if (!mount || mount.nodeType !== 1) return;
    var slotKey = resolveSlotKey(mount);
    if (!slotKey) return;

    var cached = slotCache[slotKey];
    if (cached && cached.status === "ok") {
      applyToMount(mount, slotKey, cached.items);
      return;
    }
    if (cached && cached.status === "err") {
      hideEmptyMount(mount);
      return;
    }
    fetchSlot(slotKey).then(function () {
      schedule();
    });
  }

  function fillPageRoleMount(mount, slotKey) {
    if (!mount) return;
    if (!slotKey) {
      clearAndHideMount(mount);
      return;
    }
    var cached = slotCache[slotKey];
    if (cached && cached.status === "ok") {
      applyToMount(mount, slotKey, cached.items);
      return;
    }
    if (cached && cached.status === "err") {
      clearAndHideMount(mount);
      return;
    }
    fetchSlot(slotKey).then(function (entry) {
      if (!entry || entry.status !== "ok" || !entry.items.length) {
        clearAndHideMount(mount);
        return;
      }
      applyToMount(mount, slotKey, entry.items);
    });
  }

  /**
   * Event Hook may inject feat-style native stacks (no data-cas-ad-slot).
   * When present, skip cas_page role fill to avoid double ads.
   */
  function hasNativePageStack(slotKey) {
    if (!slotKey) return false;
    var id = "ad_" + String(slotKey).split(".").join("_") + "_wrap";
    var el = document.getElementById(id);
    if (!el) return false;
    if (el.getAttribute("data-cas-ad-slot") || el.getAttribute("data-cas-hero-slot")) {
      return false;
    }
    return true;
  }

  function runPageMounts() {
    var resolved = resolvePageSlots();
    var pageSig =
      (resolved.path || "") +
      "|" +
      (resolved.top || "") +
      "|" +
      (resolved.bottom || "") +
      "|" +
      (resolved.excluded ? "1" : "0");

    var tops = findPageRoleMounts("page-top");
    var bottoms = findPageRoleMounts("page-bottom");

    if (resolved.excluded || (!resolved.top && !resolved.bottom)) {
      for (var i = 0; i < tops.length; i++) clearAndHideMount(tops[i]);
      for (var j = 0; j < bottoms.length; j++) clearAndHideMount(bottoms[j]);
      lastPageSig = pageSig;
      return;
    }

    // If path/slot pair changed, force rebuild (clear old sig)
    if (pageSig !== lastPageSig) {
      for (var t = 0; t < tops.length; t++) {
        tops[t].removeAttribute("data-cas-ad-sig");
      }
      for (var b = 0; b < bottoms.length; b++) {
        bottoms[b].removeAttribute("data-cas-ad-sig");
      }
      lastPageSig = pageSig;
    }

    for (var ti = 0; ti < tops.length; ti++) {
      if (hasNativePageStack(resolved.top)) {
        clearAndHideMount(tops[ti]);
      } else {
        fillPageRoleMount(tops[ti], resolved.top);
      }
    }
    for (var bi = 0; bi < bottoms.length; bi++) {
      if (hasNativePageStack(resolved.bottom)) {
        clearAndHideMount(bottoms[bi]);
      } else {
        fillPageRoleMount(bottoms[bi], resolved.bottom);
      }
    }
  }

  function run() {
    try {
      removeLegacySiblingHosts();
      runPageMounts();
      var mounts = findMounts();
      for (var i = 0; i < mounts.length; i++) {
        enhanceMount(mounts[i]);
      }
    } catch (e) {}
  }

  var scheduled = null;
  var debounceTimer = null;
  function schedule() {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(function () {
      debounceTimer = null;
      if (scheduled) return;
      scheduled = requestAnimationFrame
        ? requestAnimationFrame(function () {
            scheduled = null;
            run();
          })
        : setTimeout(function () {
            scheduled = null;
            run();
          }, 16);
    }, 120);
  }

  function onNav() {
    // Path changed — allow remount even if cache warm
    lastPageSig = "";
    schedule();
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", schedule);
  else schedule();
  setInterval(run, 1500);
  try {
    window.addEventListener("popstate", onNav);
  } catch (e) {}
  try {
    // Patch pushState/replaceState for SPA navigations
    var _ps = history.pushState;
    var _rs = history.replaceState;
    if (typeof _ps === "function") {
      history.pushState = function () {
        var r = _ps.apply(this, arguments);
        onNav();
        return r;
      };
    }
    if (typeof _rs === "function") {
      history.replaceState = function () {
        var r = _rs.apply(this, arguments);
        onNav();
        return r;
      };
    }
  } catch (e2) {}
  try {
    new MutationObserver(schedule).observe(document.documentElement, {
      childList: true,
      subtree: true,
    });
  } catch (e3) {}
})();
