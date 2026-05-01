<?php

declare(strict_types=1);

namespace Drupal\visitor_view\Plugin\TopBarItem;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Url;
use Drupal\navigation\Attribute\TopBarItem;
use Drupal\navigation\TopBarItemBase;
use Drupal\navigation\TopBarRegion;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Visitor View preview button in the Top Bar.
 */
#[TopBarItem(
  id: 'visitor_view_preview',
  region: TopBarRegion::Actions,
  weight: -10,
  label: new TranslatableMarkup('Visitor View Preview'),
)]
final class VisitorViewPreview extends TopBarItemBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ThemeManagerInterface $themeManager,
    protected ConfigFactoryInterface $configFactory,
    protected RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('theme.manager'),
      $container->get('config.factory'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $config = $this->configFactory->get('visitor_view.settings');
    $display_location = $config->get('display_location') ?: 'top_bar';

    if ($display_location !== 'top_bar') {
      return [];
    }

    $route_name = $this->routeMatch->getRouteName() ?? '';

    if (str_ends_with($route_name, '.preview')) {
      return [];
    }

    if ($route = $this->routeMatch->getRouteObject()) {
      $controller = $route->getDefault('_controller');
      if (is_string($controller) && str_contains($controller, 'view_modes_display')) {
        return [];
      }
    }

    $active_theme = $this->themeManager->getActiveTheme()->getName();
    $default_theme = $this->configFactory->get('system.theme')->get('default');

    if ($active_theme !== $default_theme) {
      return [];
    }

    try {
      $url = Url::fromRoute('<current>', [], [
        'query' => ['visitor_view' => 1],
        'absolute' => TRUE,
      ]);
    }
    catch (\Exception $e) {
      return [];
    }

    $button_label = $config->get('button_label') ?: 'Preview site';
    $button_icon = $config->get('button_icon') ?: 'arrow-square-out';

    return [
      '#type' => 'component',
      '#component' => 'navigation:toolbar-button',
      '#props' => [
        'html_tag' => 'a',
        'text' => $button_label,
        'icon' => [
          'pack_id' => 'visitor_view',
          'icon_id' => $button_icon,
        ],
        'attributes' => new Attribute([
          'href' => $url->toString(),
          'title' => $this->t('Open this page in a new tab without admin tools'),
          'target' => '_blank',
        ]),
      ],
      '#cache' => [
        'contexts' => ['route', 'theme'],
        'tags' => [
          'config:visitor_view.settings',
        ],
      ],
      '#attached' => [
        'library' => [
          'visitor_view/url_cleaner',
        ],
      ],
    ];
  }

}
