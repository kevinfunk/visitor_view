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
   * An array of test nodes.
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
    $this->adminUser = $this->drupalCreateUser(['access content', 'access navigation']);
  }

  /**
   * Tests the JavaScript behaviors of the module.
   */
  public function testJavascriptBehaviors(): void {
    $this->drupalLogin($this->adminUser);

    $this->drupalGet($this->nodes[1]->toUrl('canonical', ['query' => ['visitor_view' => 1]]));

    $session = $this->getSession();
    $page = $session->getPage();

    $session->wait(5000, 'window.location.search.indexOf("visitor_view") === -1');

    $this->assertStringNotContainsString('visitor_view', $session->getCurrentUrl());
    $this->assertSession()->elementNotExists('css', '.admin-toolbar');

    $node2_url = $this->nodes[2]->toUrl()->toString();
    $session->executeScript("
      let a = document.createElement('a');
      a.href = '{$node2_url}';
      a.innerText = 'Go to Node 2';
      a.id = 'test-link';
      document.body.appendChild(a);
    ");

    $page->find('css', '#test-link')->click();

    $session->wait(5000, 'document.title.indexOf("Page Two") !== -1');

    $this->assertStringNotContainsString('visitor_view', $session->getCurrentUrl());
    $this->assertSession()->elementNotExists('css', '.admin-toolbar');
    $this->assertSession()->elementExists('css', 'body.visitor-view-active');
  }

}
