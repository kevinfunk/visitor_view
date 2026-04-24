<?php

namespace Drupal\visitor_view\PageCache;

use Drupal\Core\PageCache\RequestPolicyInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Bypasses page caching when the visitor_view query parameter is present.
 */
class VisitorViewRequestPolicy implements RequestPolicyInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * Constructs a new VisitorViewRequestPolicy object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(AccountInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function check(Request $request): ?string {
    if ($request->query->has('visitor_view') && $this->currentUser->hasPermission('access navigation')) {
      return static::DENY;
    }
    return NULL;
  }

}
