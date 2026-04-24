<?php

namespace Drupal\visitor_view\PathProcessor;

use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Processes outbound paths to persist the visitor_view query parameter.
 */
class VisitorViewPathProcessor implements OutboundPathProcessorInterface {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Constructs a new VisitorViewPathProcessor object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    $current_request = $this->requestStack->getCurrentRequest();

    if (!empty($options['external'])) {
      return $path;
    }

    if ($current_request && $current_request->query->has('visitor_view')) {
      $options['query']['visitor_view'] = 1;

      if ($bubbleable_metadata) {
        $bubbleable_metadata->addCacheContexts(['url.query_args:visitor_view']);
      }
    }

    return $path;
  }

}
