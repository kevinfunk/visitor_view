<?php

namespace Drupal\visitor_view\Plugin\TopBarItem;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Url;
use Drupal\navigation\Attribute\TopBarItem;
use Drupal\navigation\TopBarItemBase;
use Drupal\navigation\TopBarRegion;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a top bar item to open the current page in visitor view.
 */
#[TopBarItem(
  id: 'visitor_view',
  region: TopBarRegion::Actions,
  weight: -10,
  label: new TranslatableMarkup('Visitor View'),
)]
class VisitorViewTopBarItem extends TopBarItemBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected ThemeManagerInterface $themeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs a new VisitorViewTopBarItem.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, ThemeManagerInterface $theme_manager, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->themeManager = $theme_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('theme.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
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

    $icon_and_text = [
      '#theme' => 'visitor_view_top_bar_link',
      '#label' => $this->t('Preview'),
    ];

    return [
      '#type' => 'link',
      '#title' => $icon_and_text,
      '#url' => $url,
      '#attributes' => [
        'title' => $this->t('Open this page in a new tab without admin tools'),
        'class' => ['top-bar__link'],
        'target' => '_blank',
        'style' => 'text-decoration: none;',
      ],
      '#cache' => [
        'contexts' => ['route', 'theme'],
      ],
      '#attached' => [
        'library' => [
          'visitor_view/url_cleaner',
        ],
      ],
    ];
  }

}
