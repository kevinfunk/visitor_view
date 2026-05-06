<?php

namespace Drupal\visitor_view\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Implements hooks for the Visitor View module.
 */
class VisitorViewHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new VisitorViewHooks object.
   */
  public function __construct(
    protected RequestStack $requestStack,
    protected AccountInterface $currentUser,
    protected ThemeManagerInterface $themeManager,
    protected ConfigFactoryInterface $configFactory,
    protected ModuleHandlerInterface $moduleHandler,
    protected RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Helper function to check if visitor view should be active.
   */
  protected function isVisitorViewActive(): bool {
    $request = $this->requestStack->getCurrentRequest();
    return $request &&
      $request->query->get('visitor_view') === '1' &&
      $this->currentUser->hasPermission('use visitor view');
  }

  /**
   * Implements hook_toolbar().
   */
  #[Hook('toolbar')]
  public function toolbar(): array {
    $items = [];

    // If Navigation module is active (uses the Top Bar plugin instead).
    if ($this->moduleHandler->moduleExists('navigation')) {
      return $items;
    }

    // If admin has configured the link to show in Local Tasks instead.
    $config = $this->configFactory->get('visitor_view.settings');
    if ($config->get('display_location') !== 'top_bar') {
      return $items;
    }

    $active_theme = $this->themeManager->getActiveTheme()->getName();
    $default_theme = $this->configFactory->get('system.theme')->get('default');

    if ($active_theme !== $default_theme) {
      return $items;
    }

    try {
      $url = Url::fromRoute('<current>', [], [
        'query' => ['visitor_view' => 1],
        'absolute' => TRUE,
      ]);
    }
    catch (\Exception $e) {
      return $items;
    }

    $button_label = $config->get('button_label') ?: 'Preview site';
    $button_icon = $config->get('button_icon') ?: 'arrow-square-out';

    $items['visitor_view'] = [
      '#type' => 'toolbar_item',
      'tab' => [
        '#type' => 'link',
        '#title' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => ['class' => ['visitor-view-top-bar-action']],
          'icon' => [
            '#type' => 'icon',
            '#pack_id' => 'visitor_view',
            '#icon_id' => $button_icon,
            '#settings' => ['size' => 16],
          ],
          'label' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $button_label,
          ],
        ],
        '#url' => $url,
        '#attributes' => [
          'title' => $this->t('Open in a new tab without admin tools'),
          'class' => [
            'toolbar-item',
            'visitor-view-toolbar-link',
          ],
          'target' => '_blank',
          'style' => 'text-decoration: none;',
        ],
      ],
      '#weight' => 999,
      '#cache' => [
        'contexts' => ['route', 'theme'],
        'tags' => ['config:visitor_view.settings'],
      ],
      '#attached' => [
        'library' => [
          'visitor_view/url_cleaner',
        ],
      ],
    ];

    return $items;
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
  public function menuLocalTasksAlter(
    array &$data,
    string $route_name,
    CacheableMetadata &$cacheability,
  ): void {
    if ($this->isVisitorViewActive()) {
      $data['tabs'] = [];
      return;
    }

    $config = $this->configFactory->get('visitor_view.settings');
    $cacheability->addCacheableDependency($config);

    if (
      $config->get('display_location') === 'local_tasks' &&
      $this->currentUser->hasPermission('use visitor view')
    ) {
      // If the page does not naturally have any tabs configured.
      if (empty($data['tabs'][0])) {
        return;
      }

      $entity_found = FALSE;
      $can_edit = FALSE;
      $primary_entity = NULL;

      foreach ($this->routeMatch->getParameters() as $parameter) {
        if ($parameter instanceof EntityInterface) {
          $entity_found = TRUE;
          $primary_entity = $parameter;
          $access_result = $parameter->access('update', $this->currentUser, TRUE);
          $cacheability->addCacheableDependency($access_result);

          if ($access_result->isAllowed()) {
            $can_edit = TRUE;
          }
          // Only check the primary entity on the route.
          break;
        }
      }

      $active_theme = $this->themeManager->getActiveTheme()->getName();
      $default = $this->configFactory->get('system.theme')->get('default');

      if ($active_theme !== $default && !$entity_found) {
        return;
      }

      // Hide tab if an entity exists on the page but user cannot edit it.
      if ($entity_found && !$can_edit) {
        return;
      }

      try {
        if ($primary_entity && $primary_entity->hasLinkTemplate('canonical')) {
          $current_route = $this->routeMatch->getRouteName();
          $canonical_route = 'entity.' . $primary_entity->getEntityTypeId() . '.canonical';

          if ($current_route === $canonical_route) {
            $url = Url::fromRoute('<current>', [], [
              'query' => ['visitor_view' => 1],
            ]);
          }
          else {
            $url = $primary_entity->toUrl('canonical', [
              'query' => ['visitor_view' => 1],
            ]);
          }
        }
        else {
          $url = Url::fromRoute('<current>', [], [
            'query' => ['visitor_view' => 1],
          ]);
        }

        $button_label = $config->get('button_label') ?: 'Preview site';

        $data['tabs'][0]['visitor_view.preview'] = [
          '#theme' => 'menu_local_task',
          '#link' => [
            'title' => $button_label,
            'url' => $url,
            'localized_options' => [
              'attributes' => [
                'target' => '_blank',
                'class' => ['visitor-view-dynamic-trigger'],
              ],
            ],
          ],
          '#weight' => 999,
          '#access' => AccessResult::allowed(),
        ];
      }
      catch (\Exception $e) {
        // Fail silently if current route cannot be generated.
      }
    }
  }

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    $attachments['#attached']['library'][] = 'visitor_view/url_cleaner';
    $attachments['#cache']['contexts'][] = 'url.query_args:visitor_view';
    $attachments['#cache']['contexts'][] = 'user.permissions';
  }

  /**
   * Implements hook_preprocess_html().
   */
  #[Hook('preprocess_html', order: Order::Last)]
  public function preprocessHtml(array &$variables): void {
    $variables['#cache']['contexts'][] = 'url.query_args:visitor_view';
    $variables['#cache']['contexts'][] = 'user.permissions';
    $variables['#cache']['tags'][] = 'config:visitor_view.settings';

    if ($this->isVisitorViewActive()) {
      $base_classes = [
        'admin-toolbar',
        'toolbar-horizontal',
        'toolbar-fixed',
        'toolbar-tray-open',
      ];

      $config = $this->configFactory->get('visitor_view.settings');
      $custom_classes = $config->get('classes_to_remove') ?? [];

      $classes_to_remove = array_merge($base_classes, $custom_classes);

      $variables['#attached']['drupalSettings']['visitorView']['classesToRemove'] =
        array_values($classes_to_remove);

      if (!isset($variables['attributes']['class'])) {
        $variables['attributes']['class'] = [];
      }
      $variables['attributes']['class'][] = 'visitor-view-active';

      if (is_array($variables['attributes']['class'])) {
        $variables['attributes']['class'] = array_diff(
          $variables['attributes']['class'],
          $classes_to_remove
        );
      }

      if (
        isset($variables['attributes']) &&
        $variables['attributes'] instanceof Attribute
      ) {
        $variables['attributes']->removeClass($classes_to_remove);
      }
    }
  }

}
