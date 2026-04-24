<?php

namespace Drupal\Tests\visitor_view\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Tests the server-side functionality of the Visitor View module.
 *
 * @group visitor_view
 */
class VisitorViewTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'navigation', 'visitor_view'];

  /**
   * A test user with permission to access navigation.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * A test node.
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
    ]);
  }

  /**
   * Tests the server-side functionality of the Visitor View module.
   */
  public function testVisitorViewHooks(): void {
    $this->drupalLogin($this->adminUser);

    $this->drupalGet($this->testNode->toUrl());
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->elementExists('css', '.admin-toolbar');
    $this->assertSession()->linkExists('Preview');
    $this->assertSession()->linkByHrefExists('?visitor_view=1');

    $this->drupalGet($this->testNode->toUrl('canonical', ['query' => ['visitor_view' => 1]]));

    $this->assertSession()->elementNotExists('css', '.admin-toolbar');
    $this->assertSession()->elementExists('css', 'body.visitor-view-active');
  }

}
