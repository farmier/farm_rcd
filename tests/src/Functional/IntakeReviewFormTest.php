<?php

declare(strict_types=1);

namespace Drupal\Tests\farm_rcd\Functional;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the intake review form.
 */
#[RunTestsInSeparateProcesses]
class IntakeReviewFormTest extends RcdTestBase {

  /**
   * Test the intake review form.
   */
  public function testIntakeReviewForm() {

    // Create a pending rcd_intake log.
    $log_storage = \Drupal::entityTypeManager()->getStorage('log');
    $log = $log_storage->create([
      'type' => 'rcd_intake',
      'status' => 'pending',
      'intake_farm_name' => 'My Example Farm',
    ]);
    $log->save();

    // Confirm that the intake review form is visible on the Log.
    $this->drupalGet('/log/' . $log->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Review Intake');

    // Confirm that the owner field was autofilled.
    $this->assertEquals($this->user->id(), $this->getSession()->getPage()->findField('owner')->getValue());

    // Decide to continue.
    $this->getSession()->getPage()->fillField('decision', 'continue');

    // Create a new farm.
    $this->getSession()->getPage()->fillField('new_farm', TRUE);

    // Confirm that the farm name field was autofilled.
    $this->assertEquals('My Example Farm', $this->getSession()->getPage()->findField('farm_name')->getValue());

    // Submit the form.
    $this->getSession()->getPage()->pressButton('Submit');

    // Reload the log.
    $log = $log_storage->load($log->id());

    // Confirm that the log owner was set to the logged-in user.
    $this->assertEquals($this->user->id(), $log->get('owner')->target_id);

    // Confirm that the log status was set to done.
    $this->assertEquals('done', $log->get('status')->value);

    // Confirm that the revision log message was set.
    $this->assertEquals('Intake reviewed by ' . $this->user->getDisplayName() . ', assigned to ' . $this->user->getDisplayName() . ', marked as done.', $log->get('revision_log_message')->value);

    // Confirm that a farm organization was created and has expected values.
    $organization_storage = \Drupal::entityTypeManager()->getStorage('organization');
    $organizations = $organization_storage->loadMultiple();
    $this->assertCount(1, $organizations);
    /** @var \Drupal\organization\Entity\OrganizationInterface $farm */
    $farm = $organizations[1];
    $this->assertEquals('farm', $farm->bundle());
    $this->assertEquals('My Example Farm', $farm->label());

    // Confirm that an RCP plan was created and has expected values.
    $plan_storage = \Drupal::entityTypeManager()->getStorage('plan');
    $plans = $plan_storage->loadMultiple();
    $this->assertCount(1, $plans);
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $plans[1];
    $this->assertEquals('rcd_rcp', $plan->bundle());
    $this->assertEquals('My Example Farm RCP', $plan->label());
    $this->assertEquals($farm->id(), $plan->get('farm')->target_id);
    $this->assertEquals($log->id(), $plan->get('intake')->target_id);
    $this->assertEquals($this->user->id(), $plan->get('owner')->target_id);

    // Confirm that the intake review form no longer shows on the log now that
    // it has a status of done.
    $this->drupalGet('/log/' . $log->id());
    $this->assertSession()->pageTextNotContains('Review intake');

    // Reset log status to pending.
    $log->set('status', 'pending');
    $log->save();

    // Confirm that we can access the intake review form again.
    $this->drupalGet('/log/' . $log->id());
    $this->assertSession()->pageTextContains('Review intake');

    // This time, continue with an existing farm organization.
    $this->getSession()->getPage()->fillField('decision', 'continue');
    $this->getSession()->getPage()->fillField('new_farm', FALSE);
    $this->assertEquals($farm->label() . ' (' . $farm->id() . ')', $this->getSession()->getPage()->findField('existing_farm')->getValue());
    $this->getSession()->getPage()->pressButton('Submit');

    // Reload the log and confirm that the status was marked as done.
    $log = $log_storage->load($log->id());
    $this->assertEquals('done', $log->get('status')->value);

    // Confirm that a new farm organization was not created.
    $organizations = $organization_storage->loadMultiple();
    $this->assertCount(1, $organizations);

    // Confirm that a new RCP plan was created and has expected values.
    $plans = $plan_storage->loadMultiple();
    $this->assertCount(2, $plans);
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $plans[2];
    $this->assertEquals('rcd_rcp', $plan->bundle());
    $this->assertEquals('My Example Farm RCP', $plan->label());
    $this->assertEquals($farm->id(), $plan->get('farm')->target_id);
    $this->assertEquals($log->id(), $plan->get('intake')->target_id);

    // Reset log status to pending.
    $log->set('status', 'pending');
    $log->save();

    // This time, abandon the intake and confirm it ends up with expected
    // values.
    $this->drupalGet('/log/' . $log->id());
    $this->assertSession()->pageTextContains('Review intake');
    $this->getSession()->getPage()->fillField('decision', 'abandon');
    $this->getSession()->getPage()->fillField('reason', 'Spam');
    $this->getSession()->getPage()->pressButton('Submit');
    $log = $log_storage->load($log->id());
    $this->assertEquals('abandoned', $log->get('status')->value);
    $this->assertEquals('Intake reviewed by ' . $this->user->getDisplayName() . ', assigned to ' . $this->user->getDisplayName() . ', marked as abandoned. Reason: Spam', $log->get('revision_log_message')->value);

    // Confirm that new farms/plans were not created.
    $organizations = $organization_storage->loadMultiple();
    $this->assertCount(1, $organizations);
    $plans = $plan_storage->loadMultiple();
    $this->assertCount(2, $plans);

    // Confirm that the intake review form no longer shows on the log now that
    // it has a status of abandoned.
    $this->drupalGet('/log/' . $log->id());
    $this->assertSession()->pageTextNotContains('Review intake');
  }

}
