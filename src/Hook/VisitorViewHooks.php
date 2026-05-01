<?php

namespace Drupal\visitor_view\Hook;

use Drupal\Core\Template\Attribute;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Implements hooks for the Visitor View module.
 */
class VisitorViewHooks {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * Constructs a new VisitorViewHooks object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(RequestStack $request_stack, AccountInterface $current_user) {
    $this->requestStack = $request_stack;
    $this->currentUser = $current_user;
  }

  /**
   * Helper function to check if visitor view should be active.
   */
  protected function isVisitorViewActive(): bool {
    $request = $this->requestStack->getCurrentRequest();
    return $request && $request->query->get('visitor_view') === '1' && $this->currentUser->hasPermission('access navigation');
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'visitor_view_top_bar_link' => [
        'variables' => [
          'label' => NULL,
        ],
      ],
    ];
  }

  /**
   * Implements hook_page_top().
   */
  #[Hook('page_top', order: Order::Last)]
  public function pageTop(array &$page_top): void {
    if ($this->isVisitorViewActive()) {
      unset($page_top['navigation']);
      unset($page_top['top_bar']);
      unset($page_top['toolbar']);
    }
  }

  /**
   * Implements hook_page_bottom().
   */
  #[Hook('page_bottom', order: Order::Last)]
  public function pageBottom(array &$page_bottom): void {
    if ($this->isVisitorViewActive()) {
      unset($page_bottom['navigation']);
      unset($page_bottom['toolbar']);
    }
  }

  /**
   * Implements hook_contextual_links_view_alter().
   */
  #[Hook('contextual_links_view_alter')]
  public function contextualLinksViewAlter(array &$element, $items): void {
    if ($this->isVisitorViewActive()) {
      $element = [];
    }
  }

  /**
   * Implements hook_menu_local_tasks_alter().
   */
  #[Hook('menu_local_tasks_alter')]
  public function menuLocalTasksAlter(array &$data, string $route_name): void {
    if ($this->isVisitorViewActive()) {
      $data['tabs'] = [];
    }
  }

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    $attachments['#attached']['library'][] = 'visitor_view/url_cleaner';
    $attachments['#cache']['contexts'][] = 'url.query_args:visitor_view';
  }

  /**
   * Implements hook_preprocess_html().
   */
  #[Hook('preprocess_html', order: Order::Last)]
  public function preprocessHtml(array &$variables): void {
    $variables['#cache']['contexts'][] = 'url.query_args:visitor_view';

    if ($this->isVisitorViewActive()) {
      $variables['attributes']['class'][] = 'visitor-view-active';

      $classes_to_remove = [
        'admin-toolbar',
        'toolbar-horizontal',
        'toolbar-fixed',
        'toolbar-tray-open',
      ];

      // $variables['attributes']['class'] is a standard PHP Array.
      if (isset($variables['attributes']['class']) && is_array($variables['attributes']['class'])) {
        $variables['attributes']['class'] = array_values(array_diff($variables['attributes']['class'], $classes_to_remove));
      }

      // $variables['attributes'] has already been cast to an Attribute object.
      if (isset($variables['attributes']) && $variables['attributes'] instanceof Attribute) {
        $variables['attributes']->removeClass($classes_to_remove);
      }
    }
  }

}
