<?php

declare(strict_types=1);

namespace Drupal\Tests\farm_rcd\Functional;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Tests\farm_rcd\Traits\PhpWordTestingTrait;
use Drupal\farm_rcd\ConservationPractices;
use Drupal\farm_rcd\RcdOptionLists;
use Drupal\plan\Entity\PlanInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Tests the planning workflow forms.
 */
#[RunTestsInSeparateProcesses]
class PlanningWorkflowFormsTest extends RcdTestBase {

  use PhpWordTestingTrait;

  /**
   * Asset storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $assetStorage;

  /**
   * File storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $fileStorage;

  /**
   * Log storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $logStorage;

  /**
   * Organization storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $organizationStorage;

  /**
   * Plan storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $planStorage;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Load entity type storages.
    $this->assetStorage = \Drupal::entityTypeManager()->getStorage('asset');
    $this->fileStorage = \Drupal::entityTypeManager()->getStorage('file');
    $this->logStorage = \Drupal::entityTypeManager()->getStorage('log');
    $this->organizationStorage = \Drupal::entityTypeManager()->getStorage('organization');
    $this->planStorage = \Drupal::entityTypeManager()->getStorage('plan');
  }

  /**
   * Test planning workflow forms.
   */
  public function testPlanningWorkflowForms() {
    $this->doTestPropertyForm();
    $this->doTestLocationsForm();
    $this->doTestSiteAssessmentsForm();
    $this->doTestPracticesForm();
    $this->doTestDocumentForm();
    $this->doTestDocumentEmailForm();
    $this->doTestStatusForm();
  }

  /**
   * Test property form.
   */
  public function doTestPropertyForm() {

    // Create a farm organization.
    /** @var \Drupal\organization\Entity\OrganizationInterface $farm */
    $farm = $this->organizationStorage->create([
      'type' => 'farm',
      'name' => $this->randomMachineName(),
    ]);
    $farm->save();

    // Create an intake log with minimum data for these tests.
    /** @var \Drupal\log\Entity\LogInterface $intake */
    $intake = $this->logStorage->create([
      'type' => 'rcd_intake',
      'intake_property_street' => '123 Fake Street',
      'intake_property_city' => 'Fake City',
      'intake_property_state' => 'AL',
      'intake_property_zip' => '123456',
      'intake_property_parcel_gps' => 'Parcel 123',
    ]);
    $intake->save();

    // Create a resource conservation plan associated with the farm and intake.
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $this->planStorage->create([
      'type' => 'rcd_rcp',
      'name' => $this->randomMachineName(),
      'farm' => [$farm],
      'intake' => [$intake],
    ]);
    $plan->save();

    // Go to the plan entity view display.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);

    // Confirm that the plan name and farm name are present.
    $this->assertSession()->pageTextContains($plan->label());
    $this->assertSession()->pageTextContains($farm->label());

    // Confirm that the property form is present.
    $this->assertSession()->pageTextContains('Property Description');
    $this->assertSession()->responseContains('Save property information');

    // Confirm that the "Property label" field is pre-populated from the intake
    // address information, and the other fields are empty.
    $this->assertSession()->fieldValueEquals('property[form][label]', '123 Fake Street, Fake City, AL, 123456');
    $this->assertSession()->fieldValueEquals('property[form][description]', '');
    $this->assertSession()->fieldValueEquals('property[form][riparian_areas]', '');
    $this->assertSession()->fieldValueEquals('property[form][wildlife]', '');
    $this->assertSession()->fieldValueEquals('property[form][boundary][value]', '');
    $this->assertSession()->fieldValueEquals('property[form][apn][0]', '');

    // Remove property address information from the intake log, reload the
    // form, and confirm that the property parcel/GPS field was used to
    // pre-populate the property label.
    $intake->set('intake_property_street', '');
    $intake->set('intake_property_street', '');
    $intake->set('intake_property_street', '');
    $intake->set('intake_property_street', '');
    $intake->save();
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('property[form][label]', 'Parcel 123');

    // Fill in the property description, riparian areas, wildlife, boundary,
    // and APN, and then submit the form.
    $this->getSession()->getPage()->fillField('property[form][description]', 'Lorem ipsum');
    $this->getSession()->getPage()->fillField('property[form][riparian_areas]', 'Dolor sit amet');
    $this->getSession()->getPage()->fillField('property[form][wildlife]', 'Consectetur adipiscing elit');
    $this->getSession()->getPage()->fillField('property[form][boundary][value]', 'POINT(-155.59217843773246 19.472231748612728)');
    $this->getSession()->getPage()->fillField('property[form][apn][0]', 'ABC123');
    $this->getSession()->getPage()->pressButton('Save property information');

    // Confirm that a message was shown to the user.
    $this->assertSession()->pageTextContains('Property description saved.');

    // Confirm that a property was created and linked to the plan.
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $this->planStorage->load($plan->id());
    $properties = $plan->get('property')->referencedEntities();
    $this->assertNotEmpty($properties[0]);

    // Confirm that the property was saved with the correct values.
    /** @var \Drupal\asset\Entity\AssetInterface $property */
    $property = $properties[0];
    $this->assertEquals('land', $property->bundle());
    $this->assertEquals('rcd_property', $property->get('land_type')->value);
    $this->assertEquals('Parcel 123', $property->label());
    $this->assertEquals('Lorem ipsum', $property->get('notes')->value);
    $this->assertEquals('Dolor sit amet', $property->get('rcd_riparian_areas')->value);
    $this->assertEquals('Consectetur adipiscing elit', $property->get('rcd_wildlife')->value);
    $this->assertEquals('POINT(-155.59217843773246 19.472231748612728)', $property->get('intrinsic_geometry')->value);
    $apns = $property->get('rcd_apn')->getValue();
    $this->assertEquals('ABC123', $apns[0]['value']);

    // Confirm that revision log messages were added to both.
    $this->assertEquals('Created property: <a href="/asset/' . $property->id() . '">' . $property->label() . '</a>.', $plan->getRevisionLogMessage());
    $this->assertEquals('Created via <a href="/plan/' . $plan->id() . '">' . $plan->label() . '</a>.', $property->getRevisionLogMessage());

    // Reload the plan entity view display and confirm that the property's
    // information is populated in the form.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('property[form][label]', 'Parcel 123');
    $this->assertSession()->fieldValueEquals('property[form][description]', 'Lorem ipsum');
    $this->assertSession()->fieldValueEquals('property[form][riparian_areas]', 'Dolor sit amet');
    $this->assertSession()->fieldValueEquals('property[form][wildlife]', 'Consectetur adipiscing elit');
    $this->assertSession()->fieldValueEquals('property[form][boundary][value]', 'POINT(-155.59217843773246 19.472231748612728)');
    $this->assertSession()->fieldValueEquals('property[form][apn][0]', 'ABC123');

    // Unlink the property from the plan.
    $plan->set('property', []);
    $plan->save();

    // Reload the plan entity view display.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);

    // Confirm that the "new or existing" radios are shown.
    $this->assertSession()->pageTextContains('Select an existing property');
    $this->assertSession()->pageTextContains('Create a new property');

    // Select "existing", then select the property, then submit the form.
    $this->getSession()->getPage()->fillField('property[new_or_existing]', 'existing');
    $this->getSession()->getPage()->fillField('property[existing_property]', $property->id());
    $this->getSession()->getPage()->pressButton('Save property information');
    $this->assertSession()->pageTextContains('Property description saved.');

    // Confirm that the property was linked to the plan.
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $this->planStorage->load($plan->id());
    $properties = $plan->get('property')->referencedEntities();
    $this->assertNotEmpty($properties[0]);
    $this->assertEquals($property->id(), $properties[0]->id());

    // Confirm that revision log messages were added to both.
    /** @var \Drupal\asset\Entity\AssetInterface $property */
    $property = $properties[0];
    $this->assertEquals('Added property: <a href="/asset/' . $property->id() . '">' . $property->label() . '</a>.', $plan->getRevisionLogMessage());
    $this->assertEquals('Added to plan: <a href="/plan/' . $plan->id() . '">' . $plan->label() . '</a>.', $property->getRevisionLogMessage());

    // Reload the plan entity view display.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);

    // Change the property information and submit the form.
    $this->getSession()->getPage()->fillField('property[form][label]', 'Updated label');
    $this->getSession()->getPage()->fillField('property[form][description]', 'Updated description');
    $this->getSession()->getPage()->fillField('property[form][riparian_areas]', 'Updated riparian areas');
    $this->getSession()->getPage()->fillField('property[form][wildlife]', 'Updated wildlife');
    $this->getSession()->getPage()->fillField('property[form][boundary][value]', '');
    $this->getSession()->getPage()->fillField('property[form][apn][0]', 'XYZ123');
    $this->getSession()->getPage()->pressButton('Save property information');
    $this->assertSession()->pageTextContains('Property description saved.');

    // Confirm that the property was updated correctly.
    /** @var \Drupal\asset\Entity\AssetInterface $property */
    $property = $this->assetStorage->load($property->id());
    $this->assertEquals('Updated label', $property->label());
    $this->assertEquals('Updated description', $property->get('notes')->value);
    $this->assertEquals('Updated riparian areas', $property->get('rcd_riparian_areas')->value);
    $this->assertEquals('Updated wildlife', $property->get('rcd_wildlife')->value);
    $this->assertEquals('', $property->get('intrinsic_geometry')->value);
    $apns = $property->get('rcd_apn')->getValue();
    $this->assertEquals('XYZ123', $apns[0]['value']);

    // Confirm that revision log messages were added to both.
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $this->planStorage->load($plan->id());
    $this->assertEquals('Updated property: <a href="/asset/' . $property->id() . '">' . $property->label() . '</a>.', $plan->getRevisionLogMessage());
    $this->assertEquals('Updated via <a href="/plan/' . $plan->id() . '">' . $plan->label() . '</a>.', $property->getRevisionLogMessage());

    // Reload the plan entity view display.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);

    // Click the "Add another APN" button, fill in the second APN field, and
    // submit.
    $this->getSession()->getPage()->pressButton('Add another APN');
    $this->getSession()->getPage()->fillField('property[form][apn][1]', 'ABC123');
    $this->getSession()->getPage()->pressButton('Save property information');
    $this->assertSession()->pageTextContains('Property description saved.');

    // Confirm that both APNs are saved.
    /** @var \Drupal\asset\Entity\AssetInterface $property */
    $property = $this->assetStorage->load($property->id());
    $apns = $property->get('rcd_apn')->getValue();
    $this->assertCount(2, $apns);
    $this->assertEquals('XYZ123', $apns[0]['value']);
    $this->assertEquals('ABC123', $apns[1]['value']);

    // Reload the plan entity view display.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);

    // Clear out the first APN and submit.
    $this->getSession()->getPage()->fillField('property[form][apn][0]', '0');
    $this->getSession()->getPage()->pressButton('Save property information');
    $this->assertSession()->pageTextContains('Property description saved.');

    // Confirm that only the second APN exists.
    /** @var \Drupal\asset\Entity\AssetInterface $property */
    $property = $this->assetStorage->load($property->id());
    $apns = $property->get('rcd_apn')->getValue();
    $this->assertCount(1, $apns);
    $this->assertEquals('ABC123', $apns[0]['value']);
  }

  /**
   * Test locations form.
   */
  public function doTestLocationsForm() {

    // Create a farm organization.
    /** @var \Drupal\organization\Entity\OrganizationInterface $farm */
    $farm = $this->organizationStorage->create([
      'type' => 'farm',
      'name' => $this->randomMachineName(),
    ]);
    $farm->save();

    // Create a resource conservation plan associated with the farm.
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $this->planStorage->create([
      'type' => 'rcd_rcp',
      'name' => $this->randomMachineName(),
      'farm' => [$farm],
    ]);
    $plan->save();

    // Go to the plan entity view display and confirm that the location form is
    // present, but not accessible yet.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Land Use Areas');
    $this->assertSession()->pageTextContains('A property description must be created before land use areas can be added.');
    $this->assertSession()->pageTextNotContains('+ Add land use area');
    $this->assertSession()->responseNotContains('Save land assets');

    // Create a property land asset associated with the farm and plan.
    /** @var \Drupal\asset\Entity\AssetInterface $property */
    $property = $this->assetStorage->create([
      'type' => 'land',
      'land_type' => 'rcd_property',
      'name' => $this->randomMachineName(),
      'farm' => [$farm],
    ]);
    $property->save();
    $plan->set('property', [$property]);
    $plan->save();

    // Reload the form and confirm that the location form is accessible.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('A property description must be created before land use areas can be added.');
    $this->assertSession()->pageTextContains('+ Add land use area');
    $this->assertSession()->responseContains('Save land assets');

    // Fill in the form and submit it.
    $this->getSession()->getPage()->fillField('locations[add][type]', 'rcd_row_crops');
    $this->getSession()->getPage()->fillField('locations[add][label]', 'Corn field');
    $this->getSession()->getPage()->fillField('locations[add][description]', 'See corn, say corn!');
    $this->getSession()->getPage()->fillField('locations[add][boundary][value]', 'POINT(-155.60893291251693 19.431635160410153)');
    $this->getSession()->getPage()->pressButton('Save land assets');

    // Confirm that a message was shown to the user.
    $this->assertSession()->pageTextContains('Land assets saved.');

    // Confirm that a land asset was created as a child of the property with
    // all expected details filled in.
    /** @var \Drupal\asset\Entity\AssetInterface[] $land_assets */
    $land_assets = $this->assetStorage->loadByProperties([
      'type' => 'land',
      'parent' => $property->id(),
      'farm' => $farm->id(),
    ]);
    $this->assertCount(1, $land_assets);
    $land_asset = reset($land_assets);
    $this->assertEquals('rcd_row_crops', $land_asset->get('land_type')->value);
    $this->assertEquals('Corn field', $land_asset->label());
    $this->assertEquals('See corn, say corn!', $land_asset->get('notes')->value);
    $this->assertEquals('POINT(-155.60893291251693 19.431635160410153)', $land_asset->get('intrinsic_geometry')->value);

    // Confirm that revision log messages were added to both.
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $this->planStorage->load($plan->id());
    $this->assertEquals('Created land asset: <a href="' . $land_asset->toUrl()->toString() . '">' . $land_asset->label() . '</a>.', $plan->getRevisionLogMessage());
    $this->assertEquals('Created via <a href="' . $plan->toUrl()->toString() . '">' . $plan->label() . '</a>.', $land_asset->getRevisionLogMessage());

    // Confirm that the saved asset was added to the form, and fields are
    // pre-filled.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Land asset: Corn field');
    $this->assertSession()->fieldValueEquals('locations[' . $land_asset->id() . '][type]', 'rcd_row_crops');
    $this->assertSession()->fieldValueEquals('locations[' . $land_asset->id() . '][label]', 'Corn field');
    $this->assertSession()->fieldValueEquals('locations[' . $land_asset->id() . '][description]', 'See corn, say corn!');
    $this->assertSession()->fieldValueEquals('locations[' . $land_asset->id() . '][boundary][value]', 'POINT(-155.60893291251693 19.431635160410153)');

    // Edit the asset's fields and submit the form.
    $this->getSession()->getPage()->fillField('locations[' . $land_asset->id() . '][type]', 'rcd_orchard');
    $this->getSession()->getPage()->fillField('locations[' . $land_asset->id() . '][label]', 'Apple orchard');
    $this->getSession()->getPage()->fillField('locations[' . $land_asset->id() . '][description]', 'History haunts him who does not honour it.');
    $this->getSession()->getPage()->fillField('locations[' . $land_asset->id() . '][boundary][value]', '');
    $this->getSession()->getPage()->pressButton('Save land assets');
    $this->assertSession()->pageTextContains('Land assets saved.');
    $this->assertSession()->pageTextContains('Land asset: Apple orchard');

    // Confirm that the new values were saved to the asset.
    /** @var \Drupal\asset\Entity\AssetInterface $land_asset */
    $land_asset = $this->assetStorage->load($land_asset->id());
    $this->assertEquals('rcd_orchard', $land_asset->get('land_type')->value);
    $this->assertEquals('Apple orchard', $land_asset->label());
    $this->assertEquals('History haunts him who does not honour it.', $land_asset->get('notes')->value);
    $this->assertEquals('', $land_asset->get('intrinsic_geometry')->value);

    // Confirm that revision log messages were added to both.
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $this->planStorage->load($plan->id());
    $this->assertEquals('Updated land asset: <a href="' . $land_asset->toUrl()->toString() . '">' . $land_asset->label() . '</a>.', $plan->getRevisionLogMessage());
    $this->assertEquals('Updated via <a href="' . $plan->toUrl()->toString() . '">' . $plan->label() . '</a>.', $land_asset->getRevisionLogMessage());

    // Confirm that adding and updating assets simultaneously works as expected.
    $this->getSession()->getPage()->fillField('locations[add][type]', 'rcd_row_crops');
    $this->getSession()->getPage()->fillField('locations[add][label]', 'Corn field');
    $this->getSession()->getPage()->fillField('locations[add][description]', 'See corn, say corn!');
    $this->getSession()->getPage()->fillField('locations[' . $land_asset->id() . '][label]', 'Pear orchard');
    $this->getSession()->getPage()->pressButton('Save land assets');
    $this->assertSession()->pageTextContains('Land assets saved.');
    $this->assertSession()->pageTextContains('Land asset: Corn field');
    $this->assertSession()->pageTextContains('Land asset: Pear orchard');

    // Reload the page and entities.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $this->planStorage->load($plan->id());
    /** @var \Drupal\asset\Entity\AssetInterface[] $land_assets */
    $land_assets = $this->assetStorage->loadByProperties([
      'type' => 'land',
      'parent' => $property->id(),
      'farm' => $farm->id(),
    ]);
    $this->assertCount(2, $land_assets);
    $updated_land_asset = reset($land_assets);
    $created_land_asset = next($land_assets);

    // Confirm that revision log messages were added to all three.
    $this->assertEquals('Created land asset: <a href="' . $created_land_asset->toUrl()->toString() . '">' . $created_land_asset->label() . '</a>. Updated land asset: <a href="' . $updated_land_asset->toUrl()->toString() . '">' . $updated_land_asset->label() . '</a>.', $plan->getRevisionLogMessage());
    $this->assertEquals('Created via <a href="' . $plan->toUrl()->toString() . '">' . $plan->label() . '</a>.', $created_land_asset->getRevisionLogMessage());
    $this->assertEquals('Updated via <a href="' . $plan->toUrl()->toString() . '">' . $plan->label() . '</a>.', $updated_land_asset->getRevisionLogMessage());

    // Confirm that submitting the form without changing any values does not
    // save the assets.
    $this->getSession()->getPage()->pressButton('Save land assets');
    $this->assertSession()->pageTextContains('No land assets saved.');
  }

  /**
   * Test site assessments form.
   */
  public function doTestSiteAssessmentsForm() {

    // Create a farm organization.
    /** @var \Drupal\organization\Entity\OrganizationInterface $farm */
    $farm = $this->organizationStorage->create([
      'type' => 'farm',
      'name' => $this->randomMachineName(),
    ]);
    $farm->save();

    // Create a property land asset associated with the farm.
    /** @var \Drupal\asset\Entity\AssetInterface $property */
    $property = $this->assetStorage->create([
      'type' => 'land',
      'land_type' => 'rcd_property',
      'name' => $this->randomMachineName(),
      'farm' => [$farm],
    ]);
    $property->save();

    // Create a resource conservation plan associated with the farm and
    // property.
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $this->planStorage->create([
      'type' => 'rcd_rcp',
      'name' => $this->randomMachineName(),
      'farm' => [$farm],
      'property' => [$property],
    ]);
    $plan->save();

    // Go to the plan entity view display and confirm that the site assessments
    // form present but is not accessible yet.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Site Assessments');
    $this->assertSession()->pageTextContains('Land use areas must be created before site assessments can be made. ');
    $this->assertSession()->pageTextNotContains('+ Add site assessment');
    $this->assertSession()->responseNotContains('Save site assessments');

    // Create a land asset to represent a location.
    /** @var \Drupal\asset\Entity\AssetInterface $location */
    $location = $this->assetStorage->create([
      'type' => 'land',
      'land_type' => 'rcd_row_crops',
      'name' => $this->randomMachineName(),
      'parent' => [$property],
      'farm' => [$farm],
    ]);
    $location->save();

    // Reload the form and confirm that the site assessments form is
    // accessible.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('Land use areas must be created before site assessments can be made.');
    $this->assertSession()->pageTextContains('+ Add site assessment');
    $this->assertSession()->responseContains('Save site assessments');

    // Fill in the form and submit it.
    $this->getSession()->getPage()->fillField('assessments[add][location]', $location->id());
    $this->getSession()->getPage()->fillField('assessments[add][land_use_history]', 'land use history');
    $this->getSession()->getPage()->fillField('assessments[add][infrastructure]', 'existing infrastructure');
    $this->getSession()->getPage()->fillField('assessments[add][priority_concerns]', 'priority environmental concerns');
    // @todo Test taxonomy terms (Tagify requires JavaScript).
    $this->getSession()->getPage()->fillField('assessments[add][notes]', 'The site has been assessed.');
    $resources = [
      'soil',
      'water',
      'plant',
      'aquatic',
      'livestock',
      'wildlife',
      'infrastructure',
    ];
    foreach ($resources as $resource) {
      $this->getSession()->getPage()->fillField('assessments[add][resources][' . $resource . '][rating]', '5');
      $this->getSession()->getPage()->fillField('assessments[add][resources][' . $resource . '][baseline]', $resource . ' baseline');
      $this->getSession()->getPage()->fillField('assessments[add][resources][' . $resource . '][goals]', $resource . ' goals');
      $this->getSession()->getPage()->fillField('assessments[add][resources][' . $resource . '][strategy]', $resource . ' strategy');
    }
    $this->getSession()->getPage()->pressButton('Save site assessments');

    // Confirm that a message was shown to the user.
    $this->assertSession()->pageTextContains('Site assessment logs saved.');

    // Confirm that a site assessment log was created with all expected details
    // filled in.
    /** @var \Drupal\log\Entity\LogInterface[] $logs */
    $logs = $this->logStorage->loadByProperties(['type' => 'rcd_site_assessment']);
    $this->assertCount(1, $logs);
    $log = reset($logs);
    $this->assertEquals($location->id(), $log->get('location')->referencedEntities()[0]->id());
    $this->assertEquals(date('m/d/Y') . ' ' . $location->label(), $log->label());
    $this->assertEquals('done', $log->get('status')->value);
    $this->assertEquals('land use history', $log->get('rcd_land_use_history')->value);
    $this->assertEquals('existing infrastructure', $log->get('rcd_infrastructure')->value);
    $this->assertEquals('priority environmental concerns', $log->get('rcd_priority_concerns')->value);
    // @todo Test taxonomy terms (Tagify requires JavaScript).
    $this->assertEquals('The site has been assessed.', $log->get('notes')->value);
    foreach ($resources as $resource) {
      $this->assertEquals(5, $log->get('rcd_' . $resource . '_rating')->value);
      $this->assertEquals($resource . ' baseline', $log->get('rcd_' . $resource . '_baseline')->value);
      $this->assertEquals($resource . ' goals', $log->get('rcd_' . $resource . '_goals')->value);
      $this->assertEquals($resource . ' strategy', $log->get('rcd_' . $resource . '_strategy')->value);
    }

    // Confirm that revision log messages were added to both.
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $this->planStorage->load($plan->id());
    $this->assertEquals('Created site assessment log: <a href="' . $log->toUrl()->toString() . '">' . $log->label() . '</a>.', $plan->getRevisionLogMessage());
    $this->assertEquals('Created via <a href="' . $plan->toUrl()->toString() . '">' . $plan->label() . '</a>.', $log->getRevisionLogMessage());

    // Confirm that the saved log was added to the form, and fields are
    // pre-filled.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Site assessment log: ' . date('m/d/Y') . ' ' . $location->label());
    $this->assertSession()->fieldValueEquals('assessments[' . $log->id() . '][location][' . $location->id() . ']', $location->id());
    $this->assertSession()->fieldValueEquals('assessments[' . $log->id() . '][date]', date('Y-m-d'));
    $this->assertSession()->fieldValueEquals('assessments[' . $log->id() . '][land_use_history]', 'land use history');
    $this->assertSession()->fieldValueEquals('assessments[' . $log->id() . '][infrastructure]', 'existing infrastructure');
    $this->assertSession()->fieldValueEquals('assessments[' . $log->id() . '][priority_concerns]', 'priority environmental concerns');
    // @todo Test taxonomy terms (Tagify requires JavaScript).
    $this->assertSession()->fieldValueEquals('assessments[' . $log->id() . '][notes]', 'The site has been assessed.');
    foreach ($resources as $resource) {
      $this->assertSession()->fieldValueEquals('assessments[' . $log->id() . '][resources][' . $resource . '][rating]', '5');
      $this->assertSession()->fieldValueEquals('assessments[' . $log->id() . '][resources][' . $resource . '][baseline]', $resource . ' baseline');
      $this->assertSession()->fieldValueEquals('assessments[' . $log->id() . '][resources][' . $resource . '][goals]', $resource . ' goals');
      $this->assertSession()->fieldValueEquals('assessments[' . $log->id() . '][resources][' . $resource . '][strategy]', $resource . ' strategy');
    }

    // Edit the log's fields and submit the form.
    $this->getSession()->getPage()->fillField('assessments[' . $log->id() . '][date]', date('Y-m-d', strtotime('tomorrow')));
    $this->getSession()->getPage()->fillField('assessments[' . $log->id() . '][land_use_history]', 'land use history!');
    $this->getSession()->getPage()->fillField('assessments[' . $log->id() . '][infrastructure]', 'existing infrastructure!');
    $this->getSession()->getPage()->fillField('assessments[' . $log->id() . '][priority_concerns]', 'priority environmental concerns!');
    // @todo Test taxonomy terms (Tagify requires JavaScript).
    $this->getSession()->getPage()->fillField('assessments[' . $log->id() . '][notes]', 'The site has been assessed!');
    foreach ($resources as $resource) {
      $this->getSession()->getPage()->fillField('assessments[' . $log->id() . '][resources][' . $resource . '][rating]', '1');
      $this->getSession()->getPage()->fillField('assessments[' . $log->id() . '][resources][' . $resource . '][baseline]', $resource . ' baseline!');
      $this->getSession()->getPage()->fillField('assessments[' . $log->id() . '][resources][' . $resource . '][goals]', $resource . ' goals!');
      $this->getSession()->getPage()->fillField('assessments[' . $log->id() . '][resources][' . $resource . '][strategy]', $resource . ' strategy!');
    }
    $this->getSession()->getPage()->pressButton('Save site assessments');
    $this->assertSession()->pageTextContains('Site assessment logs saved.');
    $this->assertSession()->pageTextContains('Site assessment log: ' . date('m/d/Y', strtotime('tomorrow')) . ' ' . $location->label());

    // Confirm that the new values were saved to the log.
    /** @var \Drupal\log\Entity\LogInterface $log */
    $log = $this->logStorage->load($log->id());
    $this->assertEquals(strtotime(date('m/d/Y', strtotime('tomorrow'))), $log->get('timestamp')->value);
    $this->assertEquals('land use history!', $log->get('rcd_land_use_history')->value);
    $this->assertEquals('existing infrastructure!', $log->get('rcd_infrastructure')->value);
    $this->assertEquals('priority environmental concerns!', $log->get('rcd_priority_concerns')->value);
    // @todo Test taxonomy terms (Tagify requires JavaScript).
    $this->assertEquals('The site has been assessed!', $log->get('notes')->value);
    foreach ($resources as $resource) {
      $this->assertEquals(1, $log->get('rcd_' . $resource . '_rating')->value);
      $this->assertEquals($resource . ' baseline!', $log->get('rcd_' . $resource . '_baseline')->value);
      $this->assertEquals($resource . ' goals!', $log->get('rcd_' . $resource . '_goals')->value);
      $this->assertEquals($resource . ' strategy!', $log->get('rcd_' . $resource . '_strategy')->value);
    }

    // Confirm that revision log messages were added to both.
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $this->planStorage->load($plan->id());
    $this->assertEquals('Updated site assessment log: <a href="' . $log->toUrl()->toString() . '">' . $log->label() . '</a>.', $plan->getRevisionLogMessage());
    $this->assertEquals('Updated via <a href="' . $plan->toUrl()->toString() . '">' . $plan->label() . '</a>.', $log->getRevisionLogMessage());

    // Confirm that adding and updating logs simultaneously works as expected.
    $this->getSession()->getPage()->fillField('assessments[add][location]', $location->id());
    $this->getSession()->getPage()->fillField('assessments[add][land_use_history]', 'land use history');
    $this->getSession()->getPage()->fillField('assessments[' . $log->id() . '][land_use_history]', 'land use history');
    $this->getSession()->getPage()->pressButton('Save site assessments');
    $this->assertSession()->pageTextContains('Site assessment logs saved.');
    $this->assertSession()->pageTextContains('Site assessment log: ' . date('m/d/Y', strtotime('today')) . ' ' . $location->label());
    $this->assertSession()->pageTextContains('Site assessment log: ' . date('m/d/Y', strtotime('tomorrow')) . ' ' . $location->label());

    // Reload the page and entities.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $this->planStorage->load($plan->id());
    /** @var \Drupal\log\Entity\LogInterface[] $logs */
    $logs = $this->logStorage->loadByProperties(['type' => 'rcd_site_assessment']);
    $this->assertCount(2, $logs);
    $updated_log = reset($logs);
    $created_log = next($logs);

    // Confirm that revision log messages were added to all three.
    $this->assertEquals('Created site assessment log: <a href="' . $created_log->toUrl()->toString() . '">' . $created_log->label() . '</a>. Updated site assessment log: <a href="' . $updated_log->toUrl()->toString() . '">' . $updated_log->label() . '</a>.', $plan->getRevisionLogMessage());
    $this->assertEquals('Created via <a href="' . $plan->toUrl()->toString() . '">' . $plan->label() . '</a>.', $created_log->getRevisionLogMessage());
    $this->assertEquals('Updated via <a href="' . $plan->toUrl()->toString() . '">' . $plan->label() . '</a>.', $updated_log->getRevisionLogMessage());

    // Confirm that submitting the form without changing any values does not
    // save the logs.
    $this->getSession()->getPage()->pressButton('Save site assessments');
    $this->assertSession()->pageTextContains('No site assessment logs saved.');
  }

  /**
   * Test practices form.
   */
  public function doTestPracticesForm() {

    // Create a farm organization.
    /** @var \Drupal\organization\Entity\OrganizationInterface $farm */
    $farm = $this->organizationStorage->create([
      'type' => 'farm',
      'name' => $this->randomMachineName(),
    ]);
    $farm->save();

    // Create a property land asset associated with the farm.
    /** @var \Drupal\asset\Entity\AssetInterface $property */
    $property = $this->assetStorage->create([
      'type' => 'land',
      'land_type' => 'rcd_property',
      'name' => $this->randomMachineName(),
      'farm' => [$farm],
    ]);
    $property->save();

    // Create a resource conservation plan associated with the farm and
    // property.
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $this->planStorage->create([
      'type' => 'rcd_rcp',
      'name' => $this->randomMachineName(),
      'farm' => [$farm],
      'property' => [$property],
      'owner' => [$this->user],
    ]);
    $plan->save();

    // Go to the plan entity view display and confirm that the practices form
    // is present but not accessible yet.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Conservation Practices');
    $this->assertSession()->pageTextContains('Land use areas must be created before conservations practices can be added.');
    $this->assertSession()->pageTextNotContains('+ Add practice');
    $this->assertSession()->responseNotContains('Save conservation practices');

    // Create a land asset to represent a location.
    /** @var \Drupal\asset\Entity\AssetInterface $location */
    $location = $this->assetStorage->create([
      'type' => 'land',
      'land_type' => 'rcd_row_crops',
      'name' => $this->randomMachineName(),
      'parent' => [$property],
      'farm' => [$farm],
    ]);
    $location->save();

    // Reload the form and confirm that the site assessments form is
    // accessible.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('Land use areas must be created before conservations practices can be added.');
    $this->assertSession()->pageTextContains('+ Add practice');
    $this->assertSession()->pageTextContains('Select a practice and click "Save conservation practices" below. Additional details about the practice can be entered after saving.');
    $this->assertSession()->responseContains('Save conservation practices');

    // Fill in the form and submit it.
    $this->getSession()->getPage()->fillField('practices[add][location]', $location->id());
    $this->getSession()->getPage()->fillField('practices[add][practice]', 'hedgerow_planting');
    $this->getSession()->getPage()->pressButton('Save conservation practices');

    // Confirm that a message was shown to the user.
    $this->assertSession()->pageTextContains('Practice implementation plans saved.');

    // Confirm that a practice implementation plan was created with all
    // expected details filled in, linked to the resource conservation plan.
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $this->planStorage->load($plan->id());
    /** @var \Drupal\plan\Entity\PlanInterface[] $practice_plans */
    $practice_plans = $plan->get('practice_implementation_plan')->referencedEntities();
    $this->assertCount(1, $practice_plans);
    $practice_plan = reset($practice_plans);
    $this->assertEquals('rcd_practice_implementation', $practice_plan->bundle());
    $this->assertEquals($farm->id(), $practice_plan->get('farm')->referencedEntities()[0]->id());
    $this->assertEquals($location->id(), $practice_plan->get('land')->referencedEntities()[0]->id());
    $this->assertEquals($plan->get('owner')->referencedEntities()[0]->id(), $practice_plan->get('owner')->referencedEntities()[0]->id());
    $this->assertEquals($location->label() . ': Hedgerow Planting', $practice_plan->label());
    $this->assertEquals('hedgerow_planting', $practice_plan->get('rcd_practice')->value);
    $this->assertTrue($practice_plan->get('geometry')->isEmpty());
    $this->assertTrue($practice_plan->get('rcd_acres')->isEmpty());
    $this->assertTrue($practice_plan->get('rcd_linear_feet')->isEmpty());
    $expected_notes = 'Establishment of dense perennial vegetation in a linear design to achieve a conservation purpose. Hedgerows must retain sufficient vertical structure throughout the year to achieve the desired function. In all cases, the width of the hedgerow must be sufficient to achieve the stated purpose. This may necessitate the establishment of more than one row of plants.';
    $this->assertEquals($expected_notes, $practice_plan->get('notes')->value);
    $this->assertTrue($practice_plan->get('rcd_funding_source')->isEmpty());
    $this->assertEquals('planning', $practice_plan->get('status')->value);

    // Confirm that revision log messages were added to both.
    $this->assertEquals('Created practice implementation plan: <a href="' . $practice_plan->toUrl()->toString() . '">' . $practice_plan->label() . '</a>.', $plan->getRevisionLogMessage());
    $this->assertEquals('Created via <a href="' . $plan->toUrl()->toString() . '">' . $plan->label() . '</a>.', $practice_plan->getRevisionLogMessage());

    // Confirm that the saved practice plan was added to the form, and fields
    // are pre-filled.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Practice implementation plan: ' . $location->label() . ': Hedgerow Planting');
    $this->assertSession()->fieldValueEquals('practices[' . $practice_plan->id() . '][location][' . $location->id() . ']', $location->id());
    $this->assertSession()->fieldValueEquals('practices[' . $practice_plan->id() . '][practice]', 'hedgerow_planting');
    $this->assertSession()->fieldValueEquals('practices[' . $practice_plan->id() . '][geometry][value]', '');
    $this->assertSession()->fieldValueEquals('practices[' . $practice_plan->id() . '][acreage]', '');
    $this->assertSession()->fieldValueEquals('practices[' . $practice_plan->id() . '][linear_feet]', '');
    $this->assertSession()->fieldValueEquals('practices[' . $practice_plan->id() . '][target_start_date]', '');
    $this->assertSession()->fieldValueEquals('practices[' . $practice_plan->id() . '][target_end_date]', '');
    $this->assertSession()->fieldValueEquals('practices[' . $practice_plan->id() . '][notes]', $expected_notes);
    $this->assertSession()->fieldValueEquals('practices[' . $practice_plan->id() . '][funding_source]', '');
    $this->assertSession()->fieldValueEquals('practices[' . $practice_plan->id() . '][status]', 'planning');

    // Edit the practice plan's fields and submit the form.
    $this->getSession()->getPage()->fillField('practices[' . $practice_plan->id() . '][geometry][value]', 'LINESTRING(-120 35,-120 36)');
    $this->getSession()->getPage()->fillField('practices[' . $practice_plan->id() . '][linear_feet]', '100.00');
    $this->getSession()->getPage()->fillField('practices[' . $practice_plan->id() . '][target_start_date]', date('Y-m-d', strtotime('today')));
    $this->getSession()->getPage()->fillField('practices[' . $practice_plan->id() . '][target_end_date]', date('Y-m-d', strtotime('tomorrow')));
    $this->getSession()->getPage()->fillField('practices[' . $practice_plan->id() . '][notes]', 'Plant lots of tillage radish.');
    $this->getSession()->getPage()->fillField('practices[' . $practice_plan->id() . '][funding_source]', 'Grant');
    $this->getSession()->getPage()->fillField('practices[' . $practice_plan->id() . '][status]', 'implementing');
    $this->getSession()->getPage()->pressButton('Save conservation practices');
    $this->assertSession()->pageTextContains('Practice implementation plans saved.');
    $this->assertSession()->pageTextContains('Practice implementation plan: ' . $location->label() . ': Hedgerow Planting');

    // Confirm that the new values were saved to the practice plan.
    /** @var \Drupal\plan\Entity\PlanInterface $practice_plan */
    $practice_plan = $this->planStorage->load($practice_plan->id());
    $this->assertEquals($farm->id(), $practice_plan->get('farm')->referencedEntities()[0]->id());
    $this->assertEquals($location->id(), $practice_plan->get('land')->referencedEntities()[0]->id());
    $this->assertEquals($location->id(), $practice_plan->get('land')->referencedEntities()[0]->id());
    $this->assertEquals($location->label() . ': Hedgerow Planting', $practice_plan->label());
    $this->assertEquals('hedgerow_planting', $practice_plan->get('rcd_practice')->value);
    $this->assertEquals('LINESTRING(-120 35,-120 36)', $practice_plan->get('geometry')->value);
    $this->assertTrue($practice_plan->get('rcd_acres')->isEmpty());
    $this->assertEquals(100, $practice_plan->get('rcd_linear_feet')->value);
    $this->assertEquals(strtotime(date('Y-m-d', strtotime('today'))), $practice_plan->get('rcd_target_start_date')->value);
    $this->assertEquals(strtotime(date('Y-m-d', strtotime('tomorrow'))), $practice_plan->get('rcd_target_end_date')->value);
    $this->assertEquals('Plant lots of tillage radish.', $practice_plan->get('notes')->value);
    $this->assertEquals('Grant', $practice_plan->get('rcd_funding_source')->value);
    $this->assertEquals('implementing', $practice_plan->get('status')->value);

    // Confirm that revision log messages were added to both.
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $this->planStorage->load($plan->id());
    $this->assertEquals('Updated practice implementation plan: <a href="' . $practice_plan->toUrl()->toString() . '">' . $practice_plan->label() . '</a>.', $plan->getRevisionLogMessage());
    $this->assertEquals('Updated via <a href="' . $plan->toUrl()->toString() . '">' . $plan->label() . '</a>.', $practice_plan->getRevisionLogMessage());

    // Confirm that adding and updating practices simultaneously works as
    // expected.
    $this->getSession()->getPage()->fillField('practices[add][location]', $location->id());
    $this->getSession()->getPage()->fillField('practices[add][practice]', 'other');
    $this->getSession()->getPage()->fillField('practices[add][practice_other]', 'Bioremediation');
    $this->getSession()->getPage()->fillField('practices[' . $practice_plan->id() . '][notes]', 'Plant lots of sunflowers.');
    $this->getSession()->getPage()->pressButton('Save conservation practices');
    $this->assertSession()->pageTextContains('Practice implementation plans saved.');
    $this->assertSession()->pageTextContains('Practice implementation plan: ' . $location->label() . ': Hedgerow Planting');
    $this->assertSession()->pageTextContains('Practice implementation plan: ' . $location->label() . ': Bioremediation');

    // Reload the page and entities.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $this->planStorage->load($plan->id());
    /** @var \Drupal\plan\Entity\PlanInterface[] $practice_plans */
    $practice_plans = $plan->get('practice_implementation_plan')->referencedEntities();
    $this->assertCount(2, $practice_plans);
    $updated_practice_plan = reset($practice_plans);
    $created_practice_plan = next($practice_plans);

    // Confirm that revision log messages were added to all three.
    $this->assertEquals('Created practice implementation plan: <a href="' . $created_practice_plan->toUrl()->toString() . '">' . $created_practice_plan->label() . '</a>. Updated practice implementation plan: <a href="' . $updated_practice_plan->toUrl()->toString() . '">' . $updated_practice_plan->label() . '</a>.', $plan->getRevisionLogMessage());
    $this->assertEquals('Created via <a href="' . $plan->toUrl()->toString() . '">' . $plan->label() . '</a>.', $created_practice_plan->getRevisionLogMessage());
    $this->assertEquals('Updated via <a href="' . $plan->toUrl()->toString() . '">' . $plan->label() . '</a>.', $updated_practice_plan->getRevisionLogMessage());

    // Confirm that editing a single practice does not remove other practices.
    // This is a regression test for: "Adding/updating a single practice
    // removes other practices from an RCP #94".
    // @see https://github.com/farmier/farm_rcd/issues/94
    $this->getSession()->getPage()->fillField('practices[' . $practice_plan->id() . '][notes]', 'Plant lots of sunflowers!');
    $this->getSession()->getPage()->pressButton('Save conservation practices');
    $this->assertSession()->pageTextContains('Practice implementation plans saved.');
    $this->assertSession()->pageTextContains('Practice implementation plan: ' . $location->label() . ': Hedgerow Planting');
    $this->assertSession()->pageTextContains('Practice implementation plan: ' . $location->label() . ': Bioremediation');

    // Confirm that submitting the form without changing any values does not
    // save the plans.
    $this->getSession()->getPage()->pressButton('Save conservation practices');
    $this->assertSession()->pageTextContains('No practice implementation plans saved.');
  }

  /**
   * Test document form.
   */
  public function doTestDocumentForm() {

    // Create an intake log with minimum data for these tests.
    /** @var \Drupal\log\Entity\LogInterface $intake */
    $intake = $this->logStorage->create([
      'type' => 'rcd_intake',
      'intake_stakeholder_name' => $this->randomMachineName(),
      'intake_stakeholder_type' => 'landowner',
      'intake_property_owner' => $this->randomMachineName(),
      'intake_property_acreage' => 100,
      'intake_stakeholder_group' => array_keys(RcdOptionLists::stakeholderGroups()),
      'intake_stakeholder_group_other' => 'Over 65',
      'intake_property_use' => array_keys(RcdOptionLists::landUses()),
      'intake_goals' => array_keys(RcdOptionLists::goals()),
      'intake_concerns' => array_keys(RcdOptionLists::concerns()),
    ]);
    $intake->save();

    // Create a farm organization.
    /** @var \Drupal\organization\Entity\OrganizationInterface $farm */
    $farm = $this->organizationStorage->create([
      'type' => 'farm',
      'name' => $this->randomMachineName(),
    ]);
    $farm->save();

    // Create a property land asset associated with the farm.
    /** @var \Drupal\asset\Entity\AssetInterface $property */
    $property = $this->assetStorage->create([
      'type' => 'land',
      'land_type' => 'rcd_property',
      'name' => $this->randomMachineName(),
      'farm' => [$farm],
      'notes' => $this->randomMachineName(),
      'rcd_apn' => [
        $this->randomMachineName(),
        $this->randomMachineName(),
      ],
      'rcd_riparian_areas' => $this->randomMachineName(),
      'rcd_wildlife' => $this->randomMachineName(),
    ]);
    $property->save();

    // Create a resource conservation plan associated with the farm, property,
    // and intake.
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $this->planStorage->create([
      'type' => 'rcd_rcp',
      'name' => 'Test RCP',
      'farm' => [$farm],
      'property' => [$property],
      'intake' => [$intake],
    ]);
    $plan->save();

    // Create a land asset to represent a location.
    /** @var \Drupal\asset\Entity\AssetInterface $location */
    $location = $this->assetStorage->create([
      'type' => 'land',
      'land_type' => 'rcd_row_crops',
      'name' => $this->randomMachineName(),
      'notes' => $this->randomMachineName(),
      'parent' => [$property],
      'farm' => [$farm],
    ]);
    $location->save();

    // Create two practice implementation plans and link them to the resource
    // conservation plan.
    $practice_plan1 = $this->planStorage->create([
      'type' => 'rcd_practice_implementation',
      'name' => $this->randomMachineName(),
      'farm' => [$farm],
      'land' => [$location],
      'notes' => $this->randomMachineName(),
      'rcd_practice' => 'cover_crop',
      'rcd_acres' => 10,
    ]);
    $practice_plan1->save();
    $practice_plan2 = $this->planStorage->create([
      'type' => 'rcd_practice_implementation',
      'name' => $this->randomMachineName(),
      'farm' => [$farm],
      'land' => [$location],
      'notes' => 'Plant a row of sunflowers!',
      'rcd_practice' => 'other',
      'rcd_practice_other' => 'Bioremediation',
      'rcd_linear_feet' => 10,
      'rcd_target_start_date' => strtotime('today'),
      'rcd_target_end_date' => strtotime('tomorrow'),
    ]);
    $practice_plan1->save();
    $plan->set('practice_implementation_plan', [$practice_plan1, $practice_plan2]);
    $plan->save();

    // Go to the plan entity view display and confirm that the document form is
    // present.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Prepare Document');
    $this->assertSession()->responseContains('Generate document from template');
    $this->assertSession()->responseContains('Save documents');

    // Confirm that no files exist.
    $files = $this->fileStorage->loadMultiple();
    $this->assertEmpty($files);

    // Click the "Generate" button and confirm that a file was created.
    $this->getSession()->getpage()->pressButton('Generate document from template');
    $this->assertSession()->pageTextContains('Document created:');
    /** @var \Drupal\file\FileInterface[] $files */
    $files = $this->fileStorage->loadMultiple();
    $this->assertCount(1, $files);
    $file = reset($files);
    $this->assertEquals('private://docs/test-rcp.docx', $file->getFileUri());

    // Confirm that submitting without a file shows a warning message.
    $this->getSession()->getpage()->pressButton('Save documents');
    $this->assertSession()->pageTextContains('No document uploaded.');

    // Get the real path to the file.
    $real_path = \Drupal::service('stream_wrapper_manager')->getViaUri($file->getFileUri())->realpath();

    // Load the file with PhpWord and confirm that it contains expected text.
    $doc = IOFactory::load($real_path);
    $this->assertDocPopulated($doc, $plan);

    // Upload the file back to the document form.
    $this->getSession()->getPage()->attachFileToField('files[document]', $real_path);
    $this->getSession()->getpage()->pressButton('Save documents');
    $this->assertSession()->pageTextContains('Document uploaded.');

    // Confirm that the file was saved and attached to plan.
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $this->planStorage->load($plan->id());
    /** @var \Drupal\file\FileInterface[] $files */
    $files = $plan->get('file')->referencedEntities();
    $this->assertCount(1, $files);
    /** @var \Drupal\file\FileInterface $file */
    $file = reset($files);
    $this->assertEquals('private://rcp/' . date('Y-m-d') . '/test-rcp.docx', $file->getFileUri());
    $this->assertEquals(1, $file->get('status')->value);

    // Confirm that a revision log messages was added to the plan.
    $this->assertEquals('Document uploaded: <a href="' . \Drupal::service('file_url_generator')->generateString($file->getFileUri()) . '">' . $file->label() . '</a>.', $plan->getRevisionLogMessage());

    // Reload the plan and confirm that the file is displayed on the page.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($file->getFilename());

    // Mark the plan as done.
    $plan->set('status', 'done');
    $plan->save();

    // Confirm that files can not be uploaded.
    $this->getSession()->getPage()->attachFileToField('files[document]', $real_path);
    $this->getSession()->getpage()->pressButton('Save documents');
    $this->assertSession()->pageTextContains('Documents can only be uploaded to plans that are in the planning stage. This plan has been marked as done.');
  }

  /**
   * Test document email form.
   */
  public function doTestDocumentEmailForm() {

    // Create a farm organization.
    /** @var \Drupal\organization\Entity\OrganizationInterface $farm */
    $farm = $this->organizationStorage->create([
      'type' => 'farm',
      'name' => $this->randomMachineName(),
    ]);
    $farm->save();

    // Create an intake log with minimum data for these tests.
    /** @var \Drupal\log\Entity\LogInterface $intake */
    $intake = $this->logStorage->create([
      'type' => 'rcd_intake',
      'intake_stakeholder_email' => 'test@test.com',
    ]);
    $intake->save();

    // Create a resource conservation plan associated with the farm and intake.
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $this->planStorage->create([
      'type' => 'rcd_rcp',
      'name' => 'Test RCP',
      'farm' => [$farm],
      'intake' => [$intake],
    ]);
    $plan->save();

    // Go to the plan entity view display and confirm that the document email
    // form is present but not accessible yet.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Email Document');
    $this->assertSession()->pageTextContains('One or more documents must be uploaded before they can be sent via email. ');
    $this->assertSession()->responseNotContains('Send email');

    // Create a file entity associated with the plan.
    $file = $this->fileStorage->create([
      'filename' => 'test.pdf',
      'uri' => 'test.pdf',
    ]);
    $file->save();
    $plan->set('file', [$file]);
    $plan->save();

    // Reload the form and confirm that the document email form is accessible.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Email Document');
    $this->assertSession()->responseContains('Send email');

    // Confirm that the email field is populated.
    $this->assertSession()->fieldValueEquals('email[email]', 'test@test.com');

    // Confirm that the documents field options are populated.
    $this->assertSession()->pageTextContains('test.pdf');

    // @todo Test email sending.
    // @todo Test plan revision message update on email send.
  }

  /**
   * Test status form.
   */
  public function doTestStatusForm() {

    // Create a farm organization.
    /** @var \Drupal\organization\Entity\OrganizationInterface $farm */
    $farm = $this->organizationStorage->create([
      'type' => 'farm',
      'name' => $this->randomMachineName(),
    ]);
    $farm->save();

    // Create a resource conservation plan associated with the farm.
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $this->planStorage->create([
      'type' => 'rcd_rcp',
      'name' => 'Test RCP',
      'farm' => [$farm],
    ]);
    $plan->save();

    // Define button text.
    $done_button = 'Mark plan as done';
    $abandon_button = 'Abandon plan';
    $planning_button = 'Revert status to planning';

    // Go to the plan entity view display and confirm that the abandon button
    // is visible, but done and planning buttons are not.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('Planning Status');
    $this->assertSession()->responseContains('Comments');
    $this->assertSession()->responseContains($abandon_button);
    $this->assertSession()->responseNotContains($done_button);
    $this->assertSession()->responseNotContains($planning_button);

    // Upload an existing file to the document form.
    /** @var \Drupal\file\FileInterface[] $files */
    $files = $this->fileStorage->loadMultiple();
    $this->assertNotEmpty($files);
    $file = reset($files);
    $real_path = \Drupal::service('stream_wrapper_manager')->getViaUri($file->getFileUri())->realpath();
    $this->getSession()->getPage()->attachFileToField('files[document]', $real_path);
    $this->getSession()->getpage()->pressButton('Save documents');
    $this->assertSession()->pageTextContains('Document uploaded.');

    // Reload the plan and confirm that done and abandon buttons are visible,
    // but the planning button is not.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains($abandon_button);
    $this->assertSession()->responseContains($done_button);
    $this->assertSession()->responseNotContains($planning_button);

    // Click the done button, confirm that a message was shown to the user, the
    // plan status was changed, and a revision log message was added.
    $this->getSession()->getPage()->pressButton($done_button);
    $this->assertSession()->pageTextContains('Plan status changed to "done".');
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $this->planStorage->load($plan->id());
    $this->assertEquals('done', $plan->get('status')->value);
    $this->assertEquals('Plan status changed to "done".', $plan->getRevisionLogMessage());

    // Reload the plan and confirm that done and abandon buttons are not
    // visible, but the planning button is.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseNotContains($abandon_button);
    $this->assertSession()->responseNotContains($done_button);
    $this->assertSession()->responseContains($planning_button);

    // Click the planning button and confirm that validation failed.
    $this->getSession()->getPage()->pressButton($planning_button);
    $this->assertSession()->pageTextContains('An explanation for this status change must be provided.');

    // Add a comment, click the planning button, and confirm that a message was
    // shown to the user, the plan status changed, and a log revision message
    // was added.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->getSession()->getPage()->fillField('status[comments]', 'Lorem ipsum.');
    $this->getSession()->getPage()->pressButton($planning_button);
    $this->assertSession()->pageTextContains('Plan status changed to "planning".');
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $this->planStorage->load($plan->id());
    $this->assertEquals('planning', $plan->get('status')->value);
    $this->assertEquals('Plan status changed to "planning". Lorem ipsum.', $plan->getRevisionLogMessage());

    // Click the abandon button and confirm that validation failed.
    $this->getSession()->getPage()->pressButton($abandon_button);
    $this->assertSession()->pageTextContains('An explanation for this status change must be provided.');

    // Add a comment, click the abandon button, and confirm that a message was
    // shown to the user, the plan status changed, and a log revision message
    // was added.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->getSession()->getPage()->fillField('status[comments]', 'Dolor sit amet.');
    $this->getSession()->getPage()->pressButton($abandon_button);
    $this->assertSession()->pageTextContains('Plan status changed to "abandoned".');
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $this->planStorage->load($plan->id());
    $this->assertEquals('abandoned', $plan->get('status')->value);
    $this->assertEquals('Plan status changed to "abandoned". Dolor sit amet.', $plan->getRevisionLogMessage());

    // Reload the plan and confirm that done and abandon buttons are not
    // visible, but the planning button is.
    $this->drupalGet('/plan/' . $plan->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseNotContains($abandon_button);
    $this->assertSession()->responseNotContains($done_button);
    $this->assertSession()->responseContains($planning_button);
  }

  /**
   * Check that a document contains all expected text.
   *
   * @param \PhpOffice\PhpWord\PhpWord $doc
   *   The document to search.
   * @param \Drupal\plan\Entity\PlanInterface $plan
   *   The plan entity.
   */
  protected function assertDocPopulated(PhpWord $doc, PlanInterface $plan) {

    // Confirm that there are no placeholders remaining in the document.
    $this->assertDocNotContainsText($doc, '${');

    // Build a list of expected strings to look for.
    $expected_strings = [
      'stakeholder_name' => $plan->get('intake')->referencedEntities()[0]->get('intake_stakeholder_name')->value,
      'stakeholder_type' => 'Landowner',
      'farm_name' => $plan->get('farm')->referencedEntities()[0]->label(),
      'property_owner' => $plan->get('intake')->referencedEntities()[0]->get('intake_property_owner')->value,
      'property_name' => $plan->get('property')->referencedEntities()[0]->label(),
      'property_description' => $plan->get('property')->referencedEntities()[0]->get('notes')->value,
      'property_apn1' => $plan->get('property')->referencedEntities()[0]->get('rcd_apn')[0]->value,
      'property_apn2' => $plan->get('property')->referencedEntities()[0]->get('rcd_apn')[1]->value,
      'property_riparian_areas' => $plan->get('property')->referencedEntities()[0]->get('rcd_riparian_areas')->value,
      'property_wildlife' => $plan->get('property')->referencedEntities()[0]->get('rcd_wildlife')->value,
      'property_acreage' => '100',
      'practice_location_name' => $plan->get('practice_implementation_plan')->referencedEntities()[0]->get('land')->referencedEntities()[0]->label(),
      'practice_location_land_type' => 'Row crops',
      'practice_location_overview' => $plan->get('practice_implementation_plan')->referencedEntities()[0]->get('land')->referencedEntities()[0]->get('notes')->value,
      'practice_name' => 'Cover crop',
      'practice_overview' => $plan->get('practice_implementation_plan')->referencedEntities()[0]->get('notes')->value,
      'practice_measurement' => '10 acres',
      'practice_start' => date('m/d/Y', strtotime('today')),
      'practice_end' => date('m/d/Y', strtotime('tomorrow')),
      'other_practice_name' => 'Bioremediation',
      'other_practice_measurement' => '10 linear feet',
      'other_practice_overview' => 'Plant a row of sunflowers!',
    ];

    // Practice benefits.
    foreach (ConservationPractices::get('cover_crop')['benefits'] as $key => $value) {
      $expected_strings['practice_benefit_' . $key] = $value;
    }

    // Practice resources.
    foreach (ConservationPractices::get('cover_crop')['resources'] as $key => $value) {
      $expected_strings['practice_resource_' . $key] = $value;
    }

    // Stakeholder groups (and other).
    foreach (RcdOptionLists::stakeholderGroups() as $key => $value) {
      $expected_strings['stakeholder_group_' . $key] = $value->render();
    }
    $expected_strings['stakeholder_group_other_value'] = 'Other: Over 65';

    // Land uses.
    foreach (RcdOptionLists::landUses() as $key => $value) {
      $expected_strings['land_use_' . $key] = $value->render();
    }

    // Goals.
    foreach (RcdOptionLists::goals() as $key => $value) {
      $expected_strings['goal_' . $key] = $value->render();
    }

    // Concerns.
    foreach (RcdOptionLists::concerns() as $key => $value) {
      $expected_strings['concern_' . $key] = $value->render();
    }

    // Test that expected strings do exist.
    foreach ($expected_strings as $name => $string) {
      $this->assertDocContainsText($doc, $string, $name);
    }
  }

}
