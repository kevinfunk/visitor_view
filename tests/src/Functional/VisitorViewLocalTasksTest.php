<?php

namespace Drupal\Tests\visitor_view\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Tests the Visitor View module's Local Tasks integration and settings.
 *
 * @group visitor_view
 */
class VisitorViewLocalTasksTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * We must enable the 'block' module to render tabs in the test environment.
   *
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'block', 'toolbar', 'visitor_view'];

  /**
   * The admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * The viewer user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $viewerUser;

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

    // Explicitly place the local tasks block so that tabs are physically
    // rendered onto the page in the test environment.
    $this->placeBlock('local_tasks_block');

    $this->drupalCreateContentType(['type' => 'page']);
    $this->testNode = $this->drupalCreateNode(['type' => 'page', 'title' => 'Test Page']);

    $this->adminUser = $this->drupalCreateUser([
      'access content',
      'edit any page content',
      'access toolbar',
      'use visitor view',
      'administer site configuration',
    ]);

    // Create a secondary user who cannot edit the node, ensuring they naturally
    // only possess a single "View" tab when visiting it.
    $this->viewerUser = $this->drupalCreateUser([
      'access content',
      'use visitor view',
    ]);
  }

  /**
   * Tests that the configuration toggle moves the link correctly.
   */
  public function testLocalTasksConfiguration(): void {
    $this->drupalLogin($this->adminUser);

    // 1. Verify default state (Top Bar).
    $this->drupalGet($this->testNode->toUrl());
    // The top bar/toolbar link should exist.
    $this->assertSession()->elementExists('css', '.visitor-view-toolbar-link');
    // The dynamic local task should NOT exist.
    $this->assertSession()->elementNotExists('css', '.visitor-view-dynamic-trigger');

    // 2. Change the setting to Local Tasks via the UI.
    $this->drupalGet('admin/config/user-interface/visitor-view');
    $this->submitForm([
      'display_location' => 'local_tasks',
      'button_label' => 'Preview site (Task)',
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // 3. Verify the link moved to Local Tasks on a Node page for an admin.
    $this->drupalGet($this->testNode->toUrl());
    // The top bar/toolbar link should be GONE.
    $this->assertSession()->elementNotExists('css', '.visitor-view-toolbar-link');
    // The local task link should now exist with our custom label.
    $this->assertSession()->elementExists('css', '.visitor-view-dynamic-trigger');
    $this->assertSession()->linkExists('Preview site (Task)');

    // 4. Verify the link does NOT appear on pages without existing tabs.
    // We log in as the viewer and check the exact same node. Because they don't
    // have an "Edit" tab, our module should gracefully abort.
    $this->drupalLogin($this->viewerUser);
    $this->drupalGet($this->testNode->toUrl());
    $this->assertSession()->elementNotExists('css', '.visitor-view-dynamic-trigger');
  }

  /**
   * Tests that the local task correctly generates a canonical URL.
   *
   * This ensures that if an admin is on a sub-route (like /edit or /latest),
   * the preview link still sends them to the actual frontend page.
   */
  public function testCanonicalRouteEnforcement(): void {
    $this->drupalLogin($this->adminUser);

    // Change the setting to Local Tasks.
    $this->drupalGet('admin/config/user-interface/visitor-view');
    $this->submitForm([
      'display_location' => 'local_tasks',
    ], 'Save configuration');

    // Visit the Edit form of the node.
    $edit_url = $this->testNode->toUrl('edit-form');
    $this->drupalGet($edit_url);
    $this->assertSession()->statusCodeEquals(200);

    // Calculate the expected canonical URL with our query parameter.
    $canonical_url = $this->testNode->toUrl('canonical', ['query' => ['visitor_view' => 1]])->toString();

    // Verify the generated tab link points to the canonical URL.
    $this->assertSession()->linkByHrefExists($canonical_url);

    // Verify it did NOT blindly generate a link to the current <edit> route.
    $bad_edit_url = $this->testNode->toUrl('edit-form', ['query' => ['visitor_view' => 1]])->toString();
    $this->assertSession()->linkByHrefNotExists($bad_edit_url);
  }

}
