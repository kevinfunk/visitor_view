<img width="640" height="435" alt="visitor-view" src="https://github.com/user-attachments/assets/7d95f522-501a-485a-84f0-0160c6c717b8" />

# Visitor View
Visitor View integrates seamlessly with the Drupal Navigation module by adding a contextual **"Preview"** action to your Top Bar. Administrators often struggle to see the true site layout because the Admin UI—including the sidebar, top bar, and contextual links—injects CSS and DOM elements that alter the frontend theme's dimensions.

With one click, Visitor View launches a new tab that strips away 100% of this administrative clutter. This provides a fast, stateless way to navigate your entire site exactly as a visitor would, ensuring a true-to-life browsing experience while maintaining your active administrative session in the original tab. No more logging out or opening incognito windows just to check a layout.

## Key Features

* **Contextual Visibility:** The preview button intelligently detects the active entity context and only appears on valid frontend routes (like Nodes or Canvas pages).

* **Isolated & Non-Destructive:** Keeps your active admin session perfectly intact. You can keep editing content in your primary tab while viewing the pristine frontend in the preview tab.

* **Persistent State:** Navigating through internal links within the preview tab maintains the "Visitor" state seamlessly.

* **Clean URLs:** Uses modern JavaScript (`history.replaceState()`) to instantly scrub query parameters from the address bar, keeping shared links clean and professional.

## Requirements

* **Drupal:** ^11.0
* **Modules:** Navigation (Drupal Core)

## Configuration & Permissions

There is no configuration form required. The module works out of the box.

However, for security and cache-busting purposes, Visitor View relies on the following core permission:

* **access navigation:** Users must have this permission to see the Preview button and to safely bypass the page cache when previewing content.

## How It Works (For Developers)

Visitor View relies on a stateless, tab-isolated architecture to avoid polluting your global PHP session.

1. Clicking the Top Bar item generates a URL with a `?visitor_view=1` query parameter.

2. Server-side hooks (`hook_page_top`, `hook_preprocess_html`, etc.) detect this parameter and aggressively strip the administrative render arrays and classes from the response.

3. Client-side JavaScript intercepts the load, saves the mode to `sessionStorage` (which is strictly isolated to that specific browser tab), and uses the History API to quietly remove the query parameter from the URL bar.

4. Subsequent link clicks in that tab are intercepted and appended with the parameter natively, maintaining the preview state without global session side effects.
