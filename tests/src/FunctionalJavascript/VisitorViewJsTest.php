<?php

namespace Drupal\Tests\visitor_view\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\user\UserInterface;

/**
 * Tests the JavaScript functionality of the Visitor View module.
 *
 * @group visitor_view
 */
class VisitorViewJsTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test against the modern Navigation module.
   *
   * @var array
   */
  protected static $modules = ['node', 'navigation', 'big_pipe', 'visitor_view'];

  /**
   * The admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * Nodes created for testing.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected array $nodes = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'page']);
    $this->nodes[1] = $this->drupalCreateNode(['type' => 'page', 'title' => 'Page One']);
    $this->nodes[2] = $this->drupalCreateNode(['type' => 'page', 'title' => 'Page Two']);

    $this->adminUser = $this->drupalCreateUser([
      'access content',
      'access navigation',
      'use visitor view',
    ]);
  }

  /**
   * Tests that the JS intercepts and hides navigation appropriately.
   */
  public function testJavascriptBehaviors(): void {
    $this->drupalLogin($this->adminUser);

    $this->drupalGet($this->nodes[1]->toUrl('canonical', ['query' => ['visitor_view' => 1]]));

    $session = $this->getSession();
    $page = $session->getPage();

    // Wait for JS to clean the URL via history.replaceState()
    $session->wait(5000, 'window.location.search.indexOf("visitor_view") === -1');

    $this->assertStringNotContainsString('visitor_view', $session->getCurrentUrl());

    // Verify the navigation bar was removed using the DOM ID.
    $this->assertSession()->elementNotExists('css', '#admin-toolbar');

    // Create a dynamic link to Node 2 to simulate a user click.
    $node2_url = $this->nodes[2]->toUrl()->toString();
    $session->executeScript("
      let a = document.createElement('a');
      a.href = '{$node2_url}';
      a.innerText = 'Go to Node 2';
      a.id = 'test-link';
      document.body.appendChild(a);
    ");

    $page->find('css', '#test-link')->click();

    // Wait for the next page to load.
    $session->wait(5000, 'document.title.indexOf("Page Two") !== -1');

    // Verify state persisted across the navigation.
    $this->assertStringNotContainsString('visitor_view', $session->getCurrentUrl());
    $this->assertSession()->elementNotExists('css', '#admin-toolbar');
    $this->assertSession()->elementExists('css', 'body.visitor-view-active');
  }

  /**
   * Tests that the once() implementation prevents infinite loops in BigPipe.
   */
  public function testBigPipeLoopPrevention(): void {
    $this->drupalLogin($this->adminUser);

    // 1. Visit the node with the query parameter to trigger the initial JS.
    $this->drupalGet($this->nodes[1]->toUrl('canonical', ['query' => ['visitor_view' => 1]]));

    $session = $this->getSession();

    // 2. Wait for the initial script execution to clean the URL.
    $session->wait(5000, 'window.location.search.indexOf("visitor_view") === -1');
    $this->assertStringNotContainsString('visitor_view', $session->getCurrentUrl());

    // 3. Inject a temporary window variable into the current DOM.
    // If the page refreshes, this variable will be destroyed.
    $session->executeScript('window.visitorViewTestMarker = "survived";');

    // 4. Manually trigger Drupal.attachBehaviors again.
    // This perfectly mimics what the BigPipe module does when it streams
    // secondary chunks (like blocks or late attachments) to the page.
    $session->executeScript('Drupal.attachBehaviors(document);');

    // Wait for a moment to allow any erroneous redirects to begin executing.
    $session->wait(2000);

    // 5. Evaluate the marker.
    $marker = $session->evaluateScript('window.visitorViewTestMarker');

    // If 'once' is missing or broken, the page will have refreshed due to the
    // replaceState mismatch, making the marker null/undefined.
    $this->assertEquals('survived', $marker, 'The Javascript triggered a page refresh loop when behaviors were re-attached.');
  }

}
