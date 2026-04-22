<?php

declare(strict_types=1);

namespace Drupal\Tests\farm_rcd\FunctionalJavascript;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the practice report form.
 */
#[RunTestsInSeparateProcesses]
class PracticeReportFormTest extends RcdTestBase {

  /**
   * Test the practice report form.
   */
  public function testPracticeReportForm() {
    $plan_storage = \Drupal::entityTypeManager()->getStorage('plan');
    $org_storage = \Drupal::entityTypeManager()->getStorage('organization');

    // Confirm that the practice report form is accessible.
    $this->drupalGet('/report/practices');
    $this->assertSession()->pageTextContains('Practice implementation summary');
    $this->assertSession()->fieldValueEquals('practice', '');
    $this->assertSession()->fieldValueEquals('status', '');
    $this->assertSession()->responseContains('Generate summary');

    // Submit the form and confirm that 0 plans were analyzed.
    $this->getSession()->getPage()->pressButton('Generate summary');
    $this->assertTrue($this->assertSession()->waitForText('0 practice implementation plans analyzed.', 30000));
    $this->assertSession()->pageTextNotContains('Results');
    $this->assertSession()->pageTextNotContains('Generated: ');
    $this->assertSession()->pageTextNotContains('Practices: ');
    $this->assertSession()->pageTextNotContains('Status of implementations: ');

    // Create a farm organization.
    /** @var \Drupal\organization\Entity\OrganizationInterface $farm */
    $farm = $org_storage->create([
      'type' => 'farm',
      'name' => $this->randomMachineName(),
    ]);
    $farm->save();

    // Create two practice implementation plans.
    $plan1 = $plan_storage->create([
      'type' => 'rcd_practice_implementation',
      'farm' => [$farm],
      'rcd_practice' => 'cover_crop',
      'rcd_acres' => 8,
      'name' => $this->randomMachineName(),
      'status' => 'planning',
    ]);
    $plan1->save();
    $plan2 = $plan_storage->create([
      'type' => 'rcd_practice_implementation',
      'farm' => [$farm],
      'rcd_practice' => 'other',
      'rcd_practice_other' => 'Bioremediation',
      'rcd_linear_feet' => 250,
      'name' => $this->randomMachineName(),
      'status' => 'implementing',
    ]);
    $plan2->save();

    // Resubmit the form, confirm that 2 plans were analyzed, and summary data
    // is displayed.
    $this->drupalGet('/report/practices');
    $this->getSession()->getPage()->pressButton('Generate summary');
    $this->assertTrue($this->assertSession()->waitForText('2 practice implementation plans analyzed.', 30000));
    $this->assertSession()->pageTextContains('Generated: ' . date('Y-m-d h:i'));
    $this->assertSession()->pageTextContains('Practices: Cover Crop, Bioremediation');
    $this->assertSession()->pageTextContains('Status of implementations: Planning, Implementing');
    $this->assertSession()->pageTextContains('Total implementation plans: 2');
    $this->assertSession()->pageTextContains('Total farms: 1');
    $this->assertSession()->pageTextContains('Total acreage: 8');
    $this->assertSession()->pageTextContains('Total linear feet: 250');

    // Test filtering by practice.
    $this->drupalGet('/report/practices');
    $this->getSession()->getPage()->fillField('practice', 'cover_crop');
    $this->getSession()->getPage()->pressButton('Generate summary');
    $this->assertTrue($this->assertSession()->waitForText('1 practice implementation plans analyzed.', 30000));
    $this->assertSession()->pageTextContains('Practices: Cover Crop');
    $this->assertSession()->pageTextContains('Status of implementations: Planning');
    $this->assertSession()->pageTextContains('Total implementation plans: 1');
    $this->assertSession()->pageTextContains('Total farms: 1');
    $this->assertSession()->pageTextContains('Total acreage: 8');
    $this->assertSession()->pageTextNotContains('Total linear feet: ');

    // Test filtering by plan status.
    $this->drupalGet('/report/practices');
    $this->getSession()->getPage()->fillField('status', 'implementing');
    $this->getSession()->getPage()->pressButton('Generate summary');
    $this->assertTrue($this->assertSession()->waitForText('1 practice implementation plans analyzed.', 30000));
    $this->assertSession()->pageTextContains('Practices: Bioremediation');
    $this->assertSession()->pageTextContains('Status of implementations: Implementing');
    $this->assertSession()->pageTextContains('Total implementation plans: 1');
    $this->assertSession()->pageTextContains('Total farms: 1');
    $this->assertSession()->pageTextContains('Total linear feet: 250');
    $this->assertSession()->pageTextNotContains('Total acreage: ');
  }

}
