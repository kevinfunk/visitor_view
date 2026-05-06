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
      'edit any page content',
      'access navigation',
      'use visitor view',
      'administer site configuration',
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

  /**
   * Tests the local tasks integration when the Navigation module is active.
   *
   * The Navigation module aggressively hides 'View' tabs that point to the
   * canonical route. This test ensures our fallback to '<current>' bypasses
   * that filter so the "Preview site" link remains visible in the dropdown.
   */
  public function testNavigationLocalTasksInteraction(): void {
    $this->drupalLogin($this->adminUser);

    // 1. Change the setting to Local Tasks.
    $this->drupalGet('admin/config/user-interface/visitor-view');
    $this->submitForm([
      'display_location' => 'local_tasks',
    ], 'Save configuration');

    // 2. Visit the canonical frontend node page.
    $this->drupalGet($this->testNode->toUrl());
    $this->assertSession()->statusCodeEquals(200);

    // 3. Verify the link survived the Navigation module's route filter and
    // was successfully injected into the local tasks dropdown.
    $this->assertSession()->elementExists('css', '.visitor-view-dynamic-trigger');

    // 4. Verify the generated tab link points to the correct destination URL.
    $expected_url = $this->testNode->toUrl('canonical', ['query' => ['visitor_view' => 1]])->toString();
    $this->assertSession()->linkByHrefExists($expected_url);
  }

}
