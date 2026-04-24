<?php

namespace Drupal\visitor_view\Plugin\TopBarItem;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
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
  region: TopBarRegion::Tools,
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
   * Constructs a new VisitorViewTopBarItem.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $route_object = $this->routeMatch->getRouteObject();
    $active_entity = NULL;

    if ($route_object && $entity_type_id = $route_object->getDefault('_entity_view')) {
      $entity_type_id = explode('.', $entity_type_id)[0];
      $active_entity = $this->routeMatch->getParameter($entity_type_id);
    }

    if (!$active_entity && $this->routeMatch->getParameter('canvas_page')) {
      $active_entity = $this->routeMatch->getParameter('canvas_page');
    }

    if (!$active_entity) {
      return [];
    }

    $url = Url::fromRoute('<current>', [], [
      'query' => ['visitor_view' => 1],
      'absolute' => TRUE,
    ]);

    if ($active_entity instanceof EntityInterface) {
      try {
        $url = $active_entity->toUrl('canonical', [
          'query' => ['visitor_view' => 1],
          'absolute' => TRUE,
        ]);
      }
      catch (\Exception $e) {
        // Fallback to <current> if no canonical template exists.
      }
    }

    $icon_and_text = [
      '#type' => 'inline_template',
      '#template' => '
        <span style="display: inline-flex; align-items: center; gap: 0.4rem;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M19 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zm-7-13.5c-2.97 0-5.46 2.04-6.5 5 1.04 2.96 3.53 5 6.5 5s5.46-2.04 6.5-5c-1.04-2.96-3.53-5-6.5-5zm0 8c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm0-4c-.55 0-1 .45-1 1s.45 1 1 1 1-.45 1-1-.45-1-1-1z"></path>
          </svg>
          <span>{{ label }}</span>
        </span>
      ',
      '#context' => [
        'label' => $this->t('Preview'),
      ],
    ];

    return [
      '#type' => 'link',
      '#title' => $icon_and_text,
      '#url' => $url,
      '#attributes' => [
        'title' => $this->t('Open this page in a new tab without admin tools'),
        'class' => ['top-bar__link'],
        'target' => '_blank',
      ],
      '#cache' => [
        'contexts' => ['route'],
      ],
    ];
  }

}
