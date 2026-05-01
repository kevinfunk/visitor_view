<?php

namespace Drupal\visitor_view\Plugin\Menu;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A dynamic menu link for the Visitor View module.
 */
class VisitorViewMenuLink extends MenuLinkDefault implements ContainerFactoryPluginInterface {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu_link.static.overrides')
    );
    $instance->configFactory = $container->get('config.factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    return $this->configFactory->get('visitor_view.settings')->get('display_location') === 'menu';
  }

  /**
   * {@inheritdoc}
   */
  public function getUrlObject(): Url {
    return Url::fromRoute('<front>', [], ['query' => ['visitor_view' => 1]]);
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(): array {
    $options = parent::getOptions();
    $options['attributes']['class'][] = 'visitor-view-menu-trigger';
    $options['attributes']['target'] = '_blank';
    return $options;
  }

}
