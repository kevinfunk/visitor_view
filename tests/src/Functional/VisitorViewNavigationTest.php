<?php

namespace Drupal\Tests\visitor_view\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Tests the Visitor View module with the modern Navigation module.
 *
 * @group visitor_view
 */
class VisitorViewNavigationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The new navigation module is enabled here.
   *
   * @var array
   */
  protected static $modules = ['node', 'navigation', 'visitor_view'];

  /**
   * The admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * The test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $testNode;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'page']);
    $this->testNode = $this->drupalCreateNode(['type' => 'page', 'title' => 'Test Page']);

    $this->adminUser = $this->drupalCreateUser([
      'access content',
      'access navigation',
      'use visitor view',
    ]);
  }

  /**
   * Tests the visitor view behavior inside the Navigation module.
   */
  public function testVisitorViewNavigation(): void {
    $this->drupalLogin($this->adminUser);

    $this->drupalGet($this->testNode->toUrl());
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->elementExists('css', '#admin-toolbar');
    $this->assertSession()->linkExists('Preview site');
    $this->assertSession()->linkByHrefExists('?visitor_view=1');

    // Load the page as a visitor.
    $this->drupalGet($this->testNode->toUrl('canonical', ['query' => ['visitor_view' => 1]]));

    // Verify the navigation bar was completely removed by our hooks.
    $this->assertSession()->elementNotExists('css', '#admin-toolbar');
    $this->assertSession()->elementExists('css', 'body.visitor-view-active');
  }

}
