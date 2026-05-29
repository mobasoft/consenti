(function () {
  "use strict";

  function parseCookie(name) {
    var escaped = name.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    var match = document.cookie.match(new RegExp("(?:^|; )" + escaped + "=([^;]*)"));
    if (!match) {
      return null;
    }
    try {
      return JSON.parse(decodeURIComponent(match[1]));
    } catch (error) {
      return null;
    }
  }

  function saveCookie(name, value) {
    var expires = new Date();
    expires.setFullYear(expires.getFullYear() + 1);
    document.cookie =
      name +
      "=" +
      encodeURIComponent(JSON.stringify(value)) +
      "; expires=" +
      expires.toUTCString() +
      "; path=/; SameSite=Lax";
  }

  function getThemeColors() {
    var styles = getComputedStyle(document.documentElement);
    var primary = styles.getPropertyValue("--bs-primary").trim() || "#0d6efd";
    var text = styles.getPropertyValue("--bs-body-color").trim() || "#212529";
    var background = styles.getPropertyValue("--bs-body-bg").trim() || "#ffffff";
    return { primary: primary, text: text, background: background };
  }

  function getFabConfig(root) {
    var position = (root.getAttribute("data-fab-position") || "left").toLowerCase();
    if (["left", "center", "right"].indexOf(position) === -1) {
      position = "left";
    }
    return {
      position: position,
      bottom: root.getAttribute("data-fab-bottom") || "1rem",
      offsetX: root.getAttribute("data-fab-offset-x") || "1rem",
      zIndex: root.getAttribute("data-fab-z-index") || "9990"
    };
  }

  function loadApprovedScripts(consent) {
    var scriptNodes = document.querySelectorAll('script[type="text/plain"][data-consenti-src][data-consenti-category]');
    scriptNodes.forEach(function (node) {
      var category = node.getAttribute("data-consenti-category");
      if (!consent[category]) {
        return;
      }
      var script = document.createElement("script");
      script.src = node.getAttribute("data-consenti-src");
      script.async = node.hasAttribute("async");
      script.defer = node.hasAttribute("defer");
      node.parentNode.insertBefore(script, node.nextSibling);
      node.parentNode.removeChild(node);
    });

    var iframeNodes = document.querySelectorAll("iframe[data-consenti-src][data-consenti-category][data-consenti-blocked='1']");
    iframeNodes.forEach(function (node) {
      var category = node.getAttribute("data-consenti-category");
      if (!consent[category]) {
        return;
      }
      node.setAttribute("src", node.getAttribute("data-consenti-src"));
      node.removeAttribute("data-consenti-blocked");
      node.style.display = "";
      var placeholder = node.nextElementSibling && node.nextElementSibling.classList.contains("consenti-embed-placeholder")
        ? node.nextElementSibling
        : null;
      if (placeholder) {
        placeholder.remove();
      }
    });
  }

  function openConsentDialog(cookieName, privacyUrl, colors) {
    if (document.querySelector(".consenti-banner")) {
      return;
    }
    var currentConsent = parseCookie(cookieName);
    buildBanner(cookieName, privacyUrl, colors, currentConsent);
  }

  function buildBanner(cookieName, privacyUrl, colors, consent) {
    var banner = document.createElement("aside");
    banner.className = "consenti-banner";
    banner.innerHTML =
      '<div class="consenti-text">Wir verwenden Cookies und externe Dienste. Details in der <a href="' +
      privacyUrl +
      '">Datenschutzerklärung</a>.</div>' +
      '<div class="consenti-options">' +
      '<label><input type="checkbox" checked disabled> Notwendig</label>' +
      '<label><input type="checkbox" data-consenti-check="statistics"> Statistik</label>' +
      '<label><input type="checkbox" data-consenti-check="marketing"> Marketing</label>' +
      "</div>" +
      '<div class="consenti-actions">' +
      '<button type="button" data-consenti-action="deny">Nur notwendig</button>' +
      '<button type="button" data-consenti-action="save">Speichern</button>' +
      '<button type="button" data-consenti-action="all">Alle akzeptieren</button>' +
      "</div>";

    banner.style.setProperty("--consenti-primary", colors.primary);
    banner.style.setProperty("--consenti-text", colors.text);
    banner.style.setProperty("--consenti-bg", colors.background);

    if (consent) {
      var statisticsPreset = banner.querySelector('[data-consenti-check="statistics"]');
      var marketingPreset = banner.querySelector('[data-consenti-check="marketing"]');
      if (statisticsPreset) {
        statisticsPreset.checked = !!consent.statistics;
      }
      if (marketingPreset) {
        marketingPreset.checked = !!consent.marketing;
      }
    }

    document.body.appendChild(banner);

    banner.addEventListener("click", function (event) {
      var target = event.target;
      if (!target || !target.matches("[data-consenti-action]")) {
        return;
      }

      var statistics = banner.querySelector('[data-consenti-check="statistics"]');
      var marketing = banner.querySelector('[data-consenti-check="marketing"]');
      var decision = target.getAttribute("data-consenti-action");

      if (decision === "all") {
        statistics.checked = true;
        marketing.checked = true;
      } else if (decision === "deny") {
        statistics.checked = false;
        marketing.checked = false;
      }

      var nextConsent = {
        necessary: true,
        statistics: statistics.checked,
        marketing: marketing.checked
      };
      saveCookie(cookieName, nextConsent);
      loadApprovedScripts(nextConsent);
      banner.remove();
    });

    return banner;
  }

  function mountRevokeButton(cookieName, privacyUrl, colors, fabConfig) {
    var button = document.createElement("button");
    button.className = "consenti-fab";
    button.type = "button";
    button.setAttribute("aria-label", "Cookie-Einstellungen");
    button.title = "Cookie-Einstellungen";
    button.innerHTML = "&#x1F36A;";
    button.style.setProperty("--consenti-primary", colors.primary);
    button.style.bottom = fabConfig.bottom;
    button.style.zIndex = fabConfig.zIndex;
    button.style.left = "";
    button.style.right = "";
    button.style.transform = "";
    if (fabConfig.position === "right") {
      button.style.right = fabConfig.offsetX;
    } else if (fabConfig.position === "center") {
      button.style.left = "50%";
      button.style.transform = "translateX(-50%)";
    } else {
      button.style.left = fabConfig.offsetX;
    }
    document.body.appendChild(button);

    button.addEventListener("click", function () {
      openConsentDialog(cookieName, privacyUrl, colors);
    });
  }

  function mountEmbedActions(cookieName, privacyUrl, colors) {
    document.addEventListener("click", function (event) {
      var allowButton = event.target.closest("[data-consenti-allow-category]");
      if (allowButton) {
        var category = allowButton.getAttribute("data-consenti-allow-category");
        var consent = parseCookie(cookieName) || {
          necessary: true,
          statistics: false,
          marketing: false
        };
        consent.necessary = true;
        consent[category] = true;
        saveCookie(cookieName, consent);
        loadApprovedScripts(consent);
        return;
      }

      var settingsButton = event.target.closest("[data-consenti-open-settings]");
      if (settingsButton) {
        openConsentDialog(cookieName, privacyUrl, colors);
      }
    });
  }

  function mountBanner(root) {
    var cookieName = root.getAttribute("data-cookie-name") || "consenti_consent";
    var privacyUrl = root.getAttribute("data-privacy-url") || "/datenschutz";
    var colors = getThemeColors();
    var fabConfig = getFabConfig(root);
    var consent = parseCookie(cookieName);
    mountRevokeButton(cookieName, privacyUrl, colors, fabConfig);
    mountEmbedActions(cookieName, privacyUrl, colors);
    if (consent) {
      loadApprovedScripts(consent);
      return;
    }

    buildBanner(cookieName, privacyUrl, colors, consent);
  }

  window.addEventListener("load", function () {
    var root = document.getElementById("consenti-root");
    if (root) {
      mountBanner(root);
    }
  });
})();
