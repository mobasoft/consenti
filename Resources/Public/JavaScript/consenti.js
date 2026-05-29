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

  function loadApprovedScripts(consent) {
    var nodes = document.querySelectorAll('script[type="text/plain"][data-consenti-src][data-consenti-category]');
    nodes.forEach(function (node) {
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
  }

  function mountBanner(root) {
    var cookieName = root.getAttribute("data-cookie-name") || "consenti_consent";
    var privacyUrl = root.getAttribute("data-privacy-url") || "/datenschutz";
    var consent = parseCookie(cookieName);
    if (consent) {
      loadApprovedScripts(consent);
      return;
    }

    var colors = getThemeColors();
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
  }

  window.addEventListener("load", function () {
    var root = document.getElementById("consenti-root");
    if (root) {
      mountBanner(root);
    }
  });
})();
