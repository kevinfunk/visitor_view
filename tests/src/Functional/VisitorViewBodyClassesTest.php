<?php

namespace Drupal\Tests\visitor_view\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Tests the removal of body classes in Visitor View.
 *
 * @group visitor_view
 */
class VisitorViewBodyClassesTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Enable the Navigation module.
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
      'administer site configuration',
    ]);
  }

  /**
   * Tests empty custom classes configuration still removes base classes.
   */
  public function testEmptyCustomClasses(): void {
    $this->drupalLogin($this->adminUser);

    // 1. Explicitly save the settings form with an EMPTY textarea.
    $this->drupalGet('admin/config/user-interface/visitor-view');
    $this->submitForm([
      // Simulate user deleting all custom classes.
      'classes_to_remove' => '',
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // 2. Visit the node page normally to ensure no PHP fatal errors occur.
    $this->drupalGet($this->testNode->toUrl());
    $this->assertSession()->statusCodeEquals(200);

    // 3. Visit the node page with Visitor View active.
    $this->drupalGet($this->testNode->toUrl('canonical', ['query' => ['visitor_view' => 1]]));
    $this->assertSession()->statusCodeEquals(200);

    // 4. Verify the base classes were stripped (array merge succeeded safely).
    $this->assertSession()->elementNotExists('css', 'body.admin-toolbar');
    $this->assertSession()->elementExists('css', 'body.visitor-view-active');

    // 5. Verify the classes were passed to Javascript via drupalSettings.
    $settings = $this->getDrupalSettings();
    $removed_classes = $settings['visitorView']['classesToRemove'];
    $this->assertContains('admin-toolbar', $removed_classes);
  }

  /**
   * Tests custom classes are merged and passed to Javascript.
   */
  public function testCustomClassesMerged(): void {
    $this->drupalLogin($this->adminUser);

    // 1. Save the form with custom fictional classes.
    $this->drupalGet('admin/config/user-interface/visitor-view');
    $this->submitForm([
      'classes_to_remove' => "fake-test-class\nanother-fake-class",
    ], 'Save configuration');

    // 2. Visit the node page with Visitor View active.
    $this->drupalGet($this->testNode->toUrl('canonical', ['query' => ['visitor_view' => 1]]));

    // 3. Verify drupalSettings combined the core and custom classes.
    $settings = $this->getDrupalSettings();
    $removed_classes = $settings['visitorView']['classesToRemove'];

    // Check for custom classes.
    $this->assertContains('fake-test-class', $removed_classes);
    $this->assertContains('another-fake-class', $removed_classes);

    // Check that core base classes were retained.
    $this->assertContains('admin-toolbar', $removed_classes);
  }

}
