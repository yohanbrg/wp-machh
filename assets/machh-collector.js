/**
 * Machh Collector - Frontend tracking (pageview + smart click tracking)
 *
 * Sends pageview and button click events to admin-ajax for server-side forwarding.
 * Click tracking auto-detects conversion-relevant buttons (tel:, mailto:, maps, CTAs).
 * No jQuery dependency.
 *
 * @package Machh_WP_Plugin
 */

(function () {
  'use strict';

  // Check if machhData is available
  if (typeof machhData === 'undefined') {
    console.warn('[Machh] Configuration not found');
    return;
  }

  // Check if tracking is enabled
  if (!machhData.enabled) {
    return;
  }

  // Skip tracking for admin users
  if (machhData.isAdmin) {
    return;
  }

  // ============================================================================
  // CLICK TRACKING - AUTO-DETECTION ENGINE
  // ============================================================================

  /**
   * Conversion protocol prefixes (always tracked)
   */
  var CONVERSION_PROTOCOLS = {
    'tel:': 'phone_call',
    'mailto:': 'email_click',
    'sms:': 'sms',
    'whatsapp:': 'whatsapp',
  };

  /**
   * Conversion domain patterns -> click_type
   */
  var CONVERSION_DOMAINS = [
    // Maps / Directions
    { pattern: 'maps.google', type: 'directions' },
    { pattern: 'goo.gl/maps', type: 'directions' },
    { pattern: 'maps.app.goo', type: 'directions' },
    { pattern: 'waze.com', type: 'directions' },
    { pattern: 'maps.apple.com', type: 'directions' },
    { pattern: 'ul.waze.com', type: 'directions' },
    // WhatsApp
    { pattern: 'wa.me', type: 'whatsapp' },
    { pattern: 'api.whatsapp.com', type: 'whatsapp' },
    // Booking / Scheduling
    { pattern: 'calendly.com', type: 'booking' },
    { pattern: 'cal.com', type: 'booking' },
    { pattern: 'doctolib.fr', type: 'booking' },
    { pattern: 'planity.com', type: 'booking' },
    { pattern: 'treatwell.fr', type: 'booking' },
    { pattern: 'booksy.com', type: 'booking' },
    { pattern: 'zcal.co', type: 'booking' },
  ];

  /**
   * CTA keywords to match in button text (lowercase, multi-language FR/EN)
   */
  var CTA_KEYWORDS = [
    // FR - Contact / Appel
    'appeler',
    'nous appeler',
    'appelez',
    'appelez-nous',
    'contactez',
    'contactez-nous',
    'nous contacter',
    // FR - Directions
    "itineraire",
    "itinéraire",
    'y aller',
    'nous trouver',
    // FR - Booking
    'réserver',
    'reserver',
    'prendre rdv',
    'prendre rendez-vous',
    'prenez rendez-vous',
    // FR - Quote / Order
    'demander un devis',
    'devis gratuit',
    'obtenir un devis',
    'commander',
    'acheter',
    // FR - Download / Signup
    "s'inscrire",
    'inscription',
    'télécharger',
    'telecharger',
    // EN - Contact
    'call us',
    'call now',
    'contact us',
    'get in touch',
    // EN - Directions
    'get directions',
    'directions',
    'find us',
    // EN - Booking
    'book now',
    'book a call',
    'book appointment',
    'schedule',
    'schedule a call',
    'make an appointment',
    // EN - Quote / CTA
    'get a quote',
    'free quote',
    'request a quote',
    'get started',
    'start free trial',
    'free trial',
    'sign up',
    'buy now',
    'order now',
    'shop now',
    'add to cart',
    'download',
    'subscribe',
  ];

  /**
   * Throttle map: prevents duplicate events for same click
   * Key: click_type + click_url, Value: timestamp
   */
  var _clickThrottle = {};
  var THROTTLE_MS = 5000; // 5 seconds between same-type clicks

  // ============================================================================
  // PAGEVIEW TRACKING
  // ============================================================================

  /**
   * Send pageview tracking request
   */
  function trackPageview() {
    var data = new FormData();
    data.append('action', 'machh_pageview');
    data.append('url', window.location.href);
    data.append('referrer', document.referrer || '');

    fetch(machhData.ajax_url, {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (result) {
        if (result.success) {
          if (window.console && console.debug) {
            console.debug('[Machh] Pageview tracked:', result.data);
          }
        } else {
          if (window.console && console.warn) {
            console.warn('[Machh] Pageview tracking failed:', result.data);
          }
        }
      })
      .catch(function (error) {
        if (window.console && console.warn) {
          console.warn('[Machh] Network error:', error);
        }
      });
  }

  // ============================================================================
  // CLICK TRACKING
  // ============================================================================

  /**
   * Walk up the DOM to find the nearest trackable element (a, button, [role=button])
   *
   * @param {Element} el - Starting element
   * @returns {Element|null}
   */
  function findTrackableAncestor(el) {
    var maxDepth = 10;
    var depth = 0;

    while (el && el !== document.body && depth < maxDepth) {
      if (
        el.tagName === 'A' ||
        el.tagName === 'BUTTON' ||
        (el.getAttribute && el.getAttribute('role') === 'button') ||
        (el.getAttribute && el.getAttribute('data-machh-track') === 'click')
      ) {
        return el;
      }
      el = el.parentElement;
      depth++;
    }

    return null;
  }

  /**
   * Get the visible text of an element (cleaned up)
   *
   * @param {Element} el
   * @returns {string}
   */
  function getVisibleText(el) {
    // Prefer aria-label or data-machh-label if set
    var label =
      (el.getAttribute && el.getAttribute('data-machh-label')) ||
      (el.getAttribute && el.getAttribute('aria-label'));
    if (label) {
      return label.trim();
    }

    // Get inner text, fallback to textContent
    var text = (el.innerText || el.textContent || '').trim();

    // Clean up whitespace
    text = text.replace(/\s+/g, ' ');

    // Truncate
    if (text.length > 100) {
      text = text.substring(0, 100);
    }

    return text;
  }

  /**
   * Check if href matches a conversion protocol
   *
   * @param {string} href
   * @returns {{click_type: string}|null}
   */
  function matchProtocol(href) {
    var hrefLower = href.toLowerCase();

    for (var prefix in CONVERSION_PROTOCOLS) {
      if (CONVERSION_PROTOCOLS.hasOwnProperty(prefix)) {
        if (hrefLower.indexOf(prefix) === 0) {
          return { click_type: CONVERSION_PROTOCOLS[prefix] };
        }
      }
    }

    return null;
  }

  /**
   * Check if href matches a conversion domain
   *
   * @param {string} href
   * @returns {{click_type: string}|null}
   */
  function matchDomain(href) {
    var hrefLower = href.toLowerCase();

    for (var i = 0; i < CONVERSION_DOMAINS.length; i++) {
      if (hrefLower.indexOf(CONVERSION_DOMAINS[i].pattern) !== -1) {
        return { click_type: CONVERSION_DOMAINS[i].type };
      }
    }

    return null;
  }

  /**
   * Normalize text for keyword matching (lowercase, remove accents, trim)
   *
   * @param {string} text
   * @returns {string}
   */
  function normalizeText(text) {
    return text
      .toLowerCase()
      .replace(/[\u0300-\u036f]/g, '') // Remove diacritics (combined)
      .trim();
  }

  /**
   * Check if button text matches a CTA keyword
   *
   * @param {string} text - Visible text of the button
   * @returns {{click_type: string}|null}
   */
  function matchCTAKeyword(text) {
    var normalized = normalizeText(text);

    if (!normalized || normalized.length < 3) {
      return null;
    }

    for (var i = 0; i < CTA_KEYWORDS.length; i++) {
      var keyword = CTA_KEYWORDS[i];
      // Match if the normalized text contains the keyword
      // or if the keyword contains the normalized text (for short button labels)
      if (
        normalized.indexOf(keyword) !== -1 ||
        (normalized.length >= 4 && keyword.indexOf(normalized) !== -1)
      ) {
        return { click_type: 'cta_click' };
      }
    }

    return null;
  }

  /**
   * Classify a click event on an element
   * Returns click data object if trackable, null otherwise
   *
   * @param {Element} el
   * @returns {object|null}
   */
  function classifyClick(el) {
    // Opt-out: skip elements with data-machh-ignore
    if (el.getAttribute && el.getAttribute('data-machh-ignore') !== null) {
      return null;
    }

    var href = el.href || el.getAttribute('href') || '';
    var text = getVisibleText(el);

    // 1. Explicit opt-in via data-machh-track="click"
    if (el.getAttribute && el.getAttribute('data-machh-track') === 'click') {
      return {
        click_type: el.getAttribute('data-machh-click-type') || 'custom',
        click_label: text,
        click_url: href,
        click_element: el.tagName.toLowerCase(),
      };
    }

    // 2. Protocol match (tel:, mailto:, sms:, whatsapp:)
    var protoMatch = matchProtocol(href);
    if (protoMatch) {
      return {
        click_type: protoMatch.click_type,
        click_label: text,
        click_url: href,
        click_element: el.tagName.toLowerCase(),
      };
    }

    // 3. Domain match (maps, whatsapp, calendly, etc.)
    var domainMatch = matchDomain(href);
    if (domainMatch) {
      return {
        click_type: domainMatch.click_type,
        click_label: text,
        click_url: href,
        click_element: el.tagName.toLowerCase(),
      };
    }

    // 4. CTA keyword match in button text
    var keywordMatch = matchCTAKeyword(text);
    if (keywordMatch) {
      return {
        click_type: keywordMatch.click_type,
        click_label: text,
        click_url: href,
        click_element: el.tagName.toLowerCase(),
      };
    }

    return null;
  }

  /**
   * Build throttle key for deduplication
   *
   * @param {object} clickData
   * @returns {string}
   */
  function throttleKey(clickData) {
    return clickData.click_type + '|' + clickData.click_url;
  }

  /**
   * Check if this click should be throttled
   *
   * @param {object} clickData
   * @returns {boolean}
   */
  function isThrottled(clickData) {
    var key = throttleKey(clickData);
    var now = Date.now();
    var lastTime = _clickThrottle[key];

    if (lastTime && now - lastTime < THROTTLE_MS) {
      return true;
    }

    _clickThrottle[key] = now;
    return false;
  }

  /**
   * Send click tracking event via sendBeacon (fire-and-forget, survives navigation)
   * Falls back to fetch() if sendBeacon is not available
   *
   * @param {object} clickData
   */
  function sendClickEvent(clickData) {
    var data = new FormData();
    data.append('action', 'machh_click');
    data.append('url', window.location.href);
    data.append('referrer', document.referrer || '');
    data.append('click_type', clickData.click_type);
    data.append('click_label', clickData.click_label);
    data.append('click_url', clickData.click_url);
    data.append('click_element', clickData.click_element);

    // Prefer sendBeacon (fire-and-forget, survives page unload / tel: navigation)
    if (navigator.sendBeacon) {
      var sent = navigator.sendBeacon(machhData.ajax_url, data);
      if (sent) {
        if (window.console && console.debug) {
          console.debug('[Machh] Click tracked (beacon):', clickData.click_type, clickData.click_label);
        }
        return;
      }
      // sendBeacon can return false if payload too large — fall through to fetch
    }

    // Fallback: fire-and-forget fetch (no await, no .then)
    try {
      fetch(machhData.ajax_url, {
        method: 'POST',
        body: data,
        credentials: 'same-origin',
        keepalive: true, // Allows request to survive page navigation
      });
      if (window.console && console.debug) {
        console.debug('[Machh] Click tracked (fetch):', clickData.click_type, clickData.click_label);
      }
    } catch (e) {
      // Fail silently
    }
  }

  /**
   * Global click handler (capture phase to intercept before navigation)
   *
   * @param {Event} e
   */
  function onDocumentClick(e) {
    var target = findTrackableAncestor(e.target);
    if (!target) {
      return;
    }

    var clickData = classifyClick(target);
    if (!clickData) {
      return;
    }

    // Throttle duplicate clicks
    if (isThrottled(clickData)) {
      return;
    }

    sendClickEvent(clickData);
  }

  // ============================================================================
  // INITIALIZATION
  // ============================================================================

  /**
   * Initialize all tracking
   */
  function init() {
    // Track pageview
    trackPageview();

    // Attach click tracking listener (capture phase = runs before link navigation)
    document.addEventListener('click', onDocumentClick, true);
  }

  // Wait for DOM to be ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
