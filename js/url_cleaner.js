(function (Drupal) {
  'use strict';

  Drupal.behaviors.visitorViewUrlCleaner = {
    attach: function (context) {
      if (context !== document) {
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
        // Intercept standard link clicks.
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
            if (linkUrl.host === window.location.host && !linkUrl.searchParams.has('visitor_view')) {
              e.preventDefault();
              linkUrl.searchParams.set('visitor_view', '1');
              window.location.href = linkUrl.toString();
            }
          }
          catch {
            // Fail silently and let the browser handle the click normally.
          }
        }, true);

        // Intercept form submissions so POST requests preserve the state.
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
              // Fail silently and let the form submit normally.
            }
          }
        }, true);
      }
    }
  };

})(Drupal);
