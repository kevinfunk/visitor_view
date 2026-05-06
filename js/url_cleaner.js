(function (Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.visitorViewUrlCleaner = {
    attach: function (context) {

      const triggers = once('visitor-view-trigger', '.visitor-view-dynamic-trigger', context);
      triggers.forEach(link => {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('visitor_view', '1');
        link.href = currentUrl.toString();
      });

      if (context !== document) {
        return;
      }

      const processed = once('visitor-view-cleaner', 'html', context);
      if (processed.length === 0) {
        return;
      }

      const url = new URL(window.location.href);
      const storageKey = 'visitor_view_active';

      if (url.searchParams.get('visitor_view') === '0') {
        window.sessionStorage.removeItem(storageKey);
        url.searchParams.delete('visitor_view');
        window.history.replaceState({}, document.title, url.toString());
        return;
      }

      let isVisitorViewActive = window.sessionStorage.getItem(storageKey) === '1';

      if (url.searchParams.get('visitor_view') === '1') {
        window.sessionStorage.setItem(storageKey, '1');
        isVisitorViewActive = true;
        url.searchParams.delete('visitor_view');
        window.history.replaceState({}, document.title, url.toString());
      }
      else if (isVisitorViewActive) {
        url.searchParams.set('visitor_view', '1');
        window.location.replace(url.toString());
        return;
      }

      if (isVisitorViewActive) {
        if (typeof drupalSettings !== 'undefined' && drupalSettings.visitorView && drupalSettings.visitorView.classesToRemove) {
          const classesToScrub = drupalSettings.visitorView.classesToRemove;
          if (classesToScrub.length > 0) {
            document.body.classList.remove(...classesToScrub);
          }
        }

        if (!document.getElementById('visitor-view-exit')) {
          const exitButton = document.createElement('a');
          exitButton.id = 'visitor-view-exit';
          exitButton.className = 'visitor-view-exit-button';
          exitButton.setAttribute('role', 'button');
          exitButton.setAttribute('aria-label', Drupal.t('Exit Visitor View mode'));

          const exitUrl = new URL(window.location.href);
          exitUrl.searchParams.set('visitor_view', '0');

          exitButton.href = exitUrl.toString();
          exitButton.innerText = Drupal.t('Exit Visitor View');
          document.body.appendChild(exitButton);
        }

        document.addEventListener('click', function (e) {
          if (e.defaultPrevented) {
            return;
          }
          const target = e.target.closest('a');
          if (!target || !target.href || e.ctrlKey || e.metaKey || e.shiftKey) {
            return;
          }

          try {
            const linkUrl = new URL(target.href);

            if (linkUrl.pathname === window.location.pathname && target.getAttribute('href').startsWith('#')) {
              return;
            }

            if (linkUrl.host === window.location.host && !linkUrl.searchParams.has('visitor_view')) {
              if (linkUrl.searchParams.get('visitor_view') === '0') {
                  return;
              }

              e.preventDefault();
              linkUrl.searchParams.set('visitor_view', '1');
              window.location.href = linkUrl.toString();
            }
          }
          catch {
            // Fail silently and let the browser handle the click normally.
          }
        }, true);

        document.addEventListener('submit', function (e) {
          if (e.target && e.target.action) {
            try {
              const actionUrl = new URL(e.target.action);
              if (actionUrl.host === window.location.host && !actionUrl.searchParams.has('visitor_view')) {
                actionUrl.searchParams.set('visitor_view', '1');
                e.target.action = actionUrl.toString();
              }
            }
            catch {
              // The form action is not a parseable standard URL.
            }
          }
        }, true);
      }
    }
  };

})(Drupal, drupalSettings, once);
