/**
 * Machh Collector - Frontend pageview tracking
 *
 * Sends pageview events to admin-ajax for server-side forwarding.
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

  /**
   * Send pageview tracking request
   */
  function trackPageview() {
    var data = new FormData();
    data.append('action', 'machh_pageview');
    data.append('nonce', machhData.nonce);
    data.append('url', window.location.href);
    data.append('referrer', document.referrer || '');

    // Use fetch API (modern browsers, no jQuery)
    fetch(machhData.ajax_url, {
      method: 'POST',
      body: data,
      credentials: 'same-origin', // Include cookies
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (result) {
        if (result.success) {
          // Pageview tracked successfully
          if (window.console && console.debug) {
            console.debug('[Machh] Pageview tracked:', result.data);
          }
        } else {
          // Tracking failed
          if (window.console && console.warn) {
            console.warn('[Machh] Pageview tracking failed:', result.data);
          }
        }
      })
      .catch(function (error) {
        // Network error - fail silently
        if (window.console && console.warn) {
          console.warn('[Machh] Network error:', error);
        }
      });
  }

  /**
   * Initialize tracking when DOM is ready
   */
  function init() {
    // Track pageview once on page load
    trackPageview();
  }

  // Wait for DOM to be ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    // DOM already ready
    init();
  }
})();


