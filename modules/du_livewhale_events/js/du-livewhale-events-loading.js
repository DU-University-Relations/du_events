/**
 * @file
 * Reveals LiveWhale event widgets after their remote markup and CSS are ready.
 */

(function (Drupal, once) {
  'use strict';

  var containerSelector = '[data-du-livewhale-loading]';
  var stylesheetSelector = 'link[rel~="stylesheet"][href*="/live/resource/css/"]';
  var failOpenDelay = 10000;

  /**
   * Determines whether LiveWhale has injected meaningful widget content.
   *
   * Asset elements can arrive before the widget markup and do not establish
   * that there is content ready to reveal. All other element children and
   * non-empty text are treated as widget output so readiness does not depend
   * on a class owned by one LiveWhale widget template.
   *
   * @param {HTMLElement} widget
   *   The LiveWhale widget container.
   *
   * @return {boolean}
   *   Whether the widget contains meaningful injected content.
   */
  function hasMeaningfulContent(widget) {
    var assetElementNames = ['LINK', 'SCRIPT', 'STYLE', 'NOSCRIPT', 'TEMPLATE'];

    return Array.prototype.some.call(widget.childNodes, function (node) {
      if (node.nodeType === 3) {
        return node.textContent.trim() !== '';
      }

      if (node.nodeType !== 1) {
        return false;
      }

      return assetElementNames.indexOf(node.tagName) === -1;
    });
  }

  /**
   * Initializes one LiveWhale loading placeholder.
   *
   * @param {HTMLElement} container
   *   The module-owned container around a LiveWhale widget.
   */
  function initialize(container) {
    var widget = container.querySelector('.lwcw');
    var status = container.querySelector('.du-livewhale-events__status');
    var widgetObserver;
    var headObserver;
    var failOpenTimer;
    var complete = false;

    if (!widget) {
      return;
    }

    container.classList.add('du-livewhale-events--loading');
    container.setAttribute('aria-busy', 'true');

    /**
     * Stops observing and reveals the populated widget.
     */
    function reveal() {
      if (complete) {
        return;
      }

      complete = true;
      window.clearTimeout(failOpenTimer);
      widgetObserver.disconnect();
      headObserver.disconnect();
      container.classList.remove('du-livewhale-events--loading');
      container.classList.remove('du-livewhale-events--timed-out');
      container.classList.add('du-livewhale-events--loaded');
      container.setAttribute('aria-busy', 'false');

      if (status) {
        status.textContent = Drupal.t('Events loaded.');
      }
    }

    /**
     * Reveals the widget after two painted frames.
     */
    function revealAfterPaint() {
      window.requestAnimationFrame(function () {
        window.requestAnimationFrame(reveal);
      });
    }

    /**
     * Checks whether injected widget content and its stylesheet are ready.
     */
    function checkReady() {
      var stylesheets;

      if (!hasMeaningfulContent(widget)) {
        return;
      }

      stylesheets = document.querySelectorAll(stylesheetSelector);
      Array.prototype.forEach.call(stylesheets, function (stylesheet) {
        if (stylesheet.sheet) {
          revealAfterPaint();
        }
        else {
          stylesheet.addEventListener('load', revealAfterPaint, {once: true});
        }
      });
    }

    widgetObserver = new MutationObserver(checkReady);
    widgetObserver.observe(widget, {childList: true, subtree: true});

    headObserver = new MutationObserver(checkReady);
    headObserver.observe(document.head, {childList: true, subtree: true});

    failOpenTimer = window.setTimeout(function () {
      complete = true;
      widgetObserver.disconnect();
      headObserver.disconnect();
      container.classList.remove('du-livewhale-events--loading');
      container.classList.add('du-livewhale-events--timed-out');
      container.setAttribute('aria-busy', 'false');

      if (status) {
        status.textContent = Drupal.t('Events are taking longer than expected to load.');
      }
    }, failOpenDelay);

    checkReady();
  }

  /**
   * Prepares widgets before the external LiveWhale loader executes.
   *
   * @param {Document|HTMLElement} context
   *   Drupal behavior context.
   */
  function attach(context) {
    once('du-livewhale-events-loading', containerSelector, context).forEach(initialize);
  }

  Drupal.behaviors.duLiveWhaleEventsLoading = {
    attach: attach,
  };

  attach(document);
})(Drupal, once);
