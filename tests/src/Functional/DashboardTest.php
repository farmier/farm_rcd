<?php

declare(strict_types=1);

namespace Drupal\Tests\farm_rcd\Functional;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the RCD dashboard functionality.
 */
#[RunTestsInSeparateProcesses]
class DashboardTest extends RcdTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [

    // Add UI modules to test that our alterations work.
    'farm_ui_action',
    'farm_ui_metrics',
    'farm_ui_views',
  ];

  /**
   * Test dashboard panes.
   */
  public function testDashboard() {

    // Confirm that dashboard loads.
    $this->drupalGet('/dashboard');
    $this->assertSession()->statusCodeEquals(200);

    // Confirm that the Upcoming/Late Tasks/Metrics blocks were removed.
    $this->assertSession()->responseNotContains('farm-map-dashboard');
    $this->assertSession()->pageTextNotContains('Upcoming tasks');
    $this->assertSession()->pageTextNotContains('Late tasks');
    $this->assertSession()->pageTextNotContains('Metrics');

    // Confirm that the "Add Asset/Log/Organization/Plan" buttons were removed.
    $this->assertSession()->pageTextNotContains('Add Asset');
    $this->assertSession()->pageTextNotContains('Add Log');
    $this->assertSession()->pageTextNotContains('Add Organization');
    $this->assertSession()->pageTextNotContains('Add Plan');

    // Confirm that the "Add Intake" button was added.
    $this->assertSession()->pageTextContains('Add Intake');

    // Confirm that the "Farms" block was added.
    $this->assertSession()->pageTextContains('Farms');
    $this->assertSession()->pageTextContains('Search for farm by name.');
    $this->assertSession()->pageTextContains('View all farms');

    // Confirm that the "Pending intakes" block was added.
    $this->assertSession()->pageTextContains('Pending intakes');
    $this->assertSession()->pageTextContains('No pending intakes.');
    $this->assertSession()->pageTextContains('View all intakes');
  }

}
