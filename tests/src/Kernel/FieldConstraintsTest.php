<?php

declare(strict_types=1);

namespace Drupal\Tests\farm_rcd\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Field constraint tests for farmOS RCD module.
 *
 * @group farm
 */
#[RunTestsInSeparateProcesses]
class FieldConstraintsTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'asset',
    'entity',
    'entity_reference_validators',
    'farm_entity',
    'farm_entity_access',
    'farm_farm',
    'farm_field',
    'farm_land',
    'farm_location',
    'farm_log',
    'farm_log_asset',
    'farm_map',
    'farm_parent',
    'farm_rcd',
    'geofield',
    'log',
    'options',
    'organization',
    'plan',
    'state_machine',
    'symfony_mailer_lite',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('asset');
    $this->installEntitySchema('log');
    $this->installEntitySchema('organization');
    $this->installEntitySchema('plan');
    $this->installEntitySchema('user');
    $this->installConfig([
      'farm_farm',
      'farm_land',
      'farm_rcd',
    ]);

    // Create and login user 1 to bypass permission checks.
    $this->setUpCurrentUser([], [], TRUE);
  }

  /**
   * Test RcdImmutable constraint.
   */
  public function testRcdImmutableConstraint() {
    $entity_type_manager = $this->container->get('entity_type.manager');
    $asset_storage = $entity_type_manager->getStorage('asset');
    $log_storage = $entity_type_manager->getStorage('log');
    $organization_storage = $entity_type_manager->getStorage('organization');
    $plan_storage = $entity_type_manager->getStorage('plan');

    // Create two land assets.
    /** @var \Drupal\asset\Entity\AssetInterface $land1 */
    $land1 = $asset_storage->create([
      'type' => 'land',
      'land_type' => 'other',
      'name' => $this->randomMachineName(),
    ]);
    $land1->save();
    /** @var \Drupal\asset\Entity\AssetInterface $land2 */
    $land2 = $asset_storage->create([
      'type' => 'land',
      'land_type' => 'other',
      'name' => $this->randomMachineName(),
    ]);
    $land2->save();

    // Create two intake logs.
    /** @var \Drupal\log\Entity\LogInterface $intake1 */
    $intake1 = $log_storage->create([
      'type' => 'rcd_intake',
    ]);
    $intake1->save();
    /** @var \Drupal\log\Entity\LogInterface $intake2 */
    $intake2 = $log_storage->create([
      'type' => 'rcd_intake',
    ]);
    $intake2->save();

    // Create two farm organizations.
    /** @var \Drupal\organization\Entity\OrganizationInterface $farm1 */
    $farm1 = $organization_storage->create([
      'type' => 'farm',
      'name' => $this->randomMachineName(),
    ]);
    $farm1->save();
    /** @var \Drupal\organization\Entity\OrganizationInterface $farm2 */
    $farm2 = $organization_storage->create([
      'type' => 'farm',
      'name' => $this->randomMachineName(),
    ]);
    $farm2->save();

    // Create a rcd_rcp plan that references the first farm, land, and intake.
    /** @var \Drupal\plan\Entity\PlanInterface $plan_rcd */
    $plan_rcd = $plan_storage->create([
      'type' => 'rcd_rcp',
      'name' => $this->randomMachineName(),
      'farm' => [$farm1],
      'property' => [$land1],
      'intake' => [$intake1],
    ]);
    $plan_rcd->save();

    // Create a rcd_practice_implementation plan that references the first farm
    // and land.
    /** @var \Drupal\plan\Entity\PlanInterface $plan_practice */
    $plan_practice = $plan_storage->create([
      'type' => 'rcd_practice_implementation',
      'name' => $this->randomMachineName(),
      'farm' => [$farm1],
      'land' => [$land1],
      'rcd_practice' => 'other',
    ]);
    $plan_practice->save();

    // Confirm that none of the entities have validation errors.
    $this->assertEmpty($land1->validate());
    $this->assertEmpty($land1->validate());
    $this->assertEmpty($intake1->validate());
    $this->assertEmpty($intake2->validate());
    $this->assertEmpty($farm1->validate());
    $this->assertEmpty($farm2->validate());
    $this->assertEmpty($plan_rcd->validate());
    $this->assertEmpty($plan_practice->validate());

    // Attempt to change immutable fields and confirm that validation fails.
    $plan_rcd->set('farm', [$farm2]);
    $plan_rcd->set('property', [$land2]);
    $plan_rcd->set('intake', [$intake2]);
    $violations = $plan_rcd->validate();
    $this->assertCount(3, $violations);
    foreach ($violations as $violation) {
      /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $message */
      $message = $violation->getMessage();
      $this->assertEquals('%field cannot be changed after it has been saved.', $message->getUntranslatedString());
    }
    $plan_practice->set('farm', [$farm2]);
    $plan_practice->set('land', [$land2]);
    $violations = $plan_practice->validate();
    $this->assertCount(2, $violations);
    foreach ($violations as $violation) {
      /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $message */
      $message = $violation->getMessage();
      $this->assertEquals('%field cannot be changed after it has been saved.', $message->getUntranslatedString());
    }
  }

}
