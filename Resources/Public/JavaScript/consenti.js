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

  function normalizeConsent(consent, revision) {
    return {
      necessary: true,
      statistics: !!(consent && consent.statistics),
      marketing: !!(consent && consent.marketing),
      revision: revision || ""
    };
  }

  function getThemeColors(root) {
    var useThemeColors = (root.getAttribute("data-use-theme-colors") || "1") === "1";
    if (!useThemeColors) {
      return {
        primary: root.getAttribute("data-color-accent") || "#0d6efd",
        text: root.getAttribute("data-color-banner-text") || "#212529",
        background: root.getAttribute("data-color-banner-bg") || "#ffffff",
        onPrimary: root.getAttribute("data-color-on-accent") || "#ffffff",
        placeholderBackground: root.getAttribute("data-color-placeholder-bg") || "#f8f9fa",
        placeholderBorder: root.getAttribute("data-color-placeholder-border") || "#d0d7de"
      };
    }
    var styles = getComputedStyle(document.documentElement);
    var primary = styles.getPropertyValue("--bs-primary").trim() || "#0d6efd";
    var text = styles.getPropertyValue("--bs-body-color").trim() || "#212529";
    var background = styles.getPropertyValue("--bs-body-bg").trim() || "#ffffff";
    var placeholderBackground = styles.getPropertyValue("--bs-body-bg").trim() || "#ffffff";
    return {
      primary: primary,
      text: text,
      background: background,
      onPrimary: "#ffffff",
      placeholderBackground: placeholderBackground,
      placeholderBorder: primary
    };
  }

  function applyGlobalConsentiColors(colors) {
    var rootStyle = document.documentElement.style;
    rootStyle.setProperty("--consenti-primary", colors.primary);
    rootStyle.setProperty("--consenti-text", colors.text);
    rootStyle.setProperty("--consenti-bg", colors.background);
    rootStyle.setProperty("--consenti-on-primary", colors.onPrimary || "#ffffff");
    rootStyle.setProperty("--consenti-placeholder-bg", colors.placeholderBackground || "#f8f9fa");
    rootStyle.setProperty("--consenti-placeholder-border", colors.placeholderBorder || colors.primary || "#0d6efd");
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

  function getBannerPosition(root) {
    var position = (root.getAttribute("data-position") || "bottom").toLowerCase();
    return position === "top" ? "top" : "bottom";
  }

  function getI18n(root) {
    return {
      bannerText: root.getAttribute("data-l10n-banner-text") || "We use cookies and external services. Details in the privacy policy.",
      bannerPrivacy: root.getAttribute("data-l10n-banner-privacy") || "Privacy policy",
      optionNecessary: root.getAttribute("data-l10n-option-necessary") || "Necessary",
      optionStatistics: root.getAttribute("data-l10n-option-statistics") || "Statistics",
      optionMarketing: root.getAttribute("data-l10n-option-marketing") || "Marketing",
      actionDeny: root.getAttribute("data-l10n-action-deny") || "Necessary only",
      actionSave: root.getAttribute("data-l10n-action-save") || "Save",
      actionAll: root.getAttribute("data-l10n-action-all") || "Accept all",
      settings: root.getAttribute("data-l10n-settings") || "Cookie settings",
      placeholderMessage: root.getAttribute("data-l10n-placeholder-message") || 'This content is blocked until "{category}" is allowed.',
      placeholderBlacklist: root.getAttribute("data-l10n-placeholder-blacklist") || "This content is blocked by a Consenti blacklist rule.",
      placeholderAllow: root.getAttribute("data-l10n-placeholder-allow") || "Load content ({category})"
    };
  }

  function labelForCategory(category, i18n) {
    return category === "statistics" ? i18n.optionStatistics : i18n.optionMarketing;
  }

  function interpolateCategory(text, categoryLabel) {
    return String(text || "").replace("{category}", categoryLabel);
  }

  function loadApprovedScripts(consent) {
    var scriptNodes = document.querySelectorAll('script[type="text/plain"][data-consenti-src][data-consenti-category]');
    scriptNodes.forEach(function (node) {
      if (node.getAttribute("data-consenti-blacklist") === "1") {
        return;
      }
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
      if (node.getAttribute("data-consenti-blacklist") === "1") {
        return;
      }
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

  function openConsentDialog(cookieName, privacyUrl, colors, bannerPosition, i18n, revision) {
    if (document.querySelector(".consenti-banner")) {
      return;
    }
    var currentConsent = parseCookie(cookieName);
    buildBanner(cookieName, privacyUrl, colors, currentConsent, bannerPosition, i18n, revision);
  }

  function buildBanner(cookieName, privacyUrl, colors, consent, bannerPosition, i18n, revision) {
    var banner = document.createElement("aside");
    banner.className = "consenti-banner";
    if (bannerPosition === "top") {
      banner.classList.add("consenti-banner-top");
    }
    banner.innerHTML =
      '<div class="consenti-text">' +
      i18n.bannerText +
      ' <a href="' +
      privacyUrl +
      '">' +
      i18n.bannerPrivacy +
      "</a>.</div>" +
      '<div class="consenti-options">' +
      '<label><input type="checkbox" checked disabled> ' +
      i18n.optionNecessary +
      "</label>" +
      '<label><input type="checkbox" data-consenti-check="statistics"> ' +
      i18n.optionStatistics +
      "</label>" +
      '<label><input type="checkbox" data-consenti-check="marketing"> ' +
      i18n.optionMarketing +
      "</label>" +
      "</div>" +
      '<div class="consenti-actions">' +
      '<button type="button" data-consenti-action="deny">' +
      i18n.actionDeny +
      "</button>" +
      '<button type="button" data-consenti-action="save">' +
      i18n.actionSave +
      "</button>" +
      '<button type="button" data-consenti-action="all">' +
      i18n.actionAll +
      "</button>" +
      "</div>";

    banner.style.setProperty("--consenti-primary", colors.primary);
    banner.style.setProperty("--consenti-text", colors.text);
    banner.style.setProperty("--consenti-bg", colors.background);
    banner.style.setProperty("--consenti-on-primary", colors.onPrimary || "#ffffff");

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

      var nextConsent = normalizeConsent({
        statistics: statistics.checked,
        marketing: marketing.checked
      }, revision);
      saveCookie(cookieName, nextConsent);
      loadApprovedScripts(nextConsent);
      banner.remove();
    });

    return banner;
  }

  function mountRevokeButton(cookieName, privacyUrl, colors, fabConfig, bannerPosition, i18n, revision) {
    var button = document.createElement("button");
    button.className = "consenti-fab";
    button.type = "button";
    button.setAttribute("aria-label", i18n.settings);
    button.title = i18n.settings;
    button.innerHTML = "⚙";
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
      openConsentDialog(cookieName, privacyUrl, colors, bannerPosition, i18n, revision);
    });
  }

  function mountEmbedActions(cookieName, privacyUrl, colors, bannerPosition, i18n, revision) {
    document.addEventListener("click", function (event) {
      var allowButton = event.target.closest("[data-consenti-allow-category]");
      if (allowButton) {
        var category = allowButton.getAttribute("data-consenti-allow-category");
        var consent = normalizeConsent(parseCookie(cookieName), revision);
        consent[category] = true;
        saveCookie(cookieName, consent);
        loadApprovedScripts(consent);
        return;
      }

      var settingsButton = event.target.closest("[data-consenti-open-settings]");
      if (settingsButton) {
        openConsentDialog(cookieName, privacyUrl, colors, bannerPosition, i18n, revision);
      }
    });
  }

  function localizeEmbedPlaceholders(i18n) {
    var placeholders = document.querySelectorAll(".consenti-embed-placeholder[data-consenti-category]");
    placeholders.forEach(function (placeholder) {
      var category = placeholder.getAttribute("data-consenti-category") || "marketing";
      var categoryLabel = labelForCategory(category, i18n);
      var blockedIframe = placeholder.previousElementSibling;
      var isBlacklist = blockedIframe && blockedIframe.getAttribute("data-consenti-blacklist") === "1";

      var message = placeholder.querySelector(".consenti-embed-message");
      if (message) {
        message.textContent = isBlacklist
          ? i18n.placeholderBlacklist
          : interpolateCategory(i18n.placeholderMessage, categoryLabel);
      }

      var allowButton = placeholder.querySelector("[data-consenti-allow-category]");
      if (allowButton) {
        allowButton.textContent = interpolateCategory(i18n.placeholderAllow, categoryLabel);
      }

      var settingsButton = placeholder.querySelector("[data-consenti-open-settings]");
      if (settingsButton) {
        settingsButton.textContent = i18n.settings;
      }
    });
  }

  function mountBanner(root) {
    var cookieName = root.getAttribute("data-cookie-name") || "consenti_consent";
    var privacyUrl = root.getAttribute("data-privacy-url") || "/datenschutz";
    var colors = getThemeColors(root);
    applyGlobalConsentiColors(colors);
    var fabConfig = getFabConfig(root);
    var bannerPosition = getBannerPosition(root);
    var i18n = getI18n(root);
    var revision = root.getAttribute("data-consent-revision") || "";
    var forceReconsent = (root.getAttribute("data-force-reconsent") || "1") === "1";
    var consent = parseCookie(cookieName);
    if (consent && forceReconsent && revision && consent.revision !== revision) {
      consent = null;
      document.cookie = cookieName + "=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; SameSite=Lax";
    }
    mountRevokeButton(cookieName, privacyUrl, colors, fabConfig, bannerPosition, i18n, revision);
    mountEmbedActions(cookieName, privacyUrl, colors, bannerPosition, i18n, revision);
    localizeEmbedPlaceholders(i18n);
    if (consent) {
      loadApprovedScripts(consent);
      return;
    }

    buildBanner(cookieName, privacyUrl, colors, consent, bannerPosition, i18n, revision);
  }

  window.addEventListener("load", function () {
    var root = document.getElementById("consenti-root");
    if (root) {
      mountBanner(root);
    }
  });
})();
