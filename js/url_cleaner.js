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

      if (url.searchParams.has('visitor_view')) {
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
        const killToolbar = () => {
          document.querySelectorAll('.admin-toolbar, #navigation, #toolbar-administration').forEach(el => {
            if (el !== document.body) {
              el.remove();
            }
          });
          document.body.classList.remove('admin-toolbar', 'toolbar-horizontal', 'toolbar-fixed', 'toolbar-tray-open');
        };

        killToolbar();

        const observer = new MutationObserver(killToolbar);
        observer.observe(document.documentElement, { childList: true, subtree: true });

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
          catch (err) {
            // Ignore invalid URLs.
          }
        }, true);
      }
    }
  };

})(Drupal);
