<?php

declare(strict_types=1);

namespace Drupal\Tests\farm_rcd\Functional;

use Drupal\farm_rcd\RcdOptionLists;

/**
 * Tests the intake form functionality.
 */
class IntakeFormTest extends RcdTestBase {

  /**
   * Define form fields and example data.
   *
   * @param int|null $step
   *   The step to filter by.
   *
   * @return array
   *   Returns an array of fields and data for each step.
   */
  protected function fieldData($step = NULL) {
    $field_data = [
      1 => [
        'stakeholder[personal][name]' => 'Jane Doe',
        'stakeholder[personal][type]' => 'landowner',
        'stakeholder[personal][email]' => 'jane@example.com',
        'stakeholder[personal][phone]' => '555-5555',
        'stakeholder[personal][address][street]' => '123 Fake Street',
        'stakeholder[personal][address][city]' => 'Fakeville',
        'stakeholder[personal][address][state]' => 'AL',
        'stakeholder[personal][address][zip]' => '123456',
        'stakeholder[personal][group][beginning]' => TRUE,
        'stakeholder[personal][group][family]' => TRUE,
        'stakeholder[personal][group][female]' => TRUE,
        'stakeholder[personal][group][lgbtq]' => TRUE,
        'stakeholder[personal][group][veteran]' => TRUE,
        'stakeholder[personal][group][black]' => TRUE,
        'stakeholder[personal][group][native]' => TRUE,
        'stakeholder[personal][group][hispanic]' => TRUE,
        'stakeholder[personal][group][asian]' => TRUE,
        'stakeholder[personal][group][pacific]' => TRUE,
        'stakeholder[personal][group][optout]' => TRUE,
        'stakeholder[personal][group][other]' => TRUE,
        'stakeholder[personal][share_rcds]' => 'yes',
        'stakeholder[personal][group_other]' => 'Over 65',
      ],
      2 => [
        'property[info][farm_name]' => 'Sunflower Farm',
        'property[info][own_or_lease]' => 'own',
        'property[info][owner]' => 'My sister',
        'property[info][lease_expiration]' => NULL,
        'property[info][acreage]' => 100,
        'property[info][has_address]' => 'yes',
        'property[info][street]' => '124 Fake Street',
        'property[info][city]' => 'New Fakeville',
        'property[info][state]' => 'AK',
        'property[info][zip]' => '123457',
        'property[land_use][land_use][grazing]' => TRUE,
        'property[land_use][land_use][vineyards]' => TRUE,
        'property[land_use][land_use][orchards]' => TRUE,
        'property[land_use][land_use][rowcrops]' => TRUE,
        'property[land_use][land_use][natural]' => TRUE,
        'property[land_use][land_use][other]' => TRUE,
        'property[land_use][grazing_acreage]' => 10,
        'property[land_use][vineyards_acreage]' => 20,
        'property[land_use][orchards_acreage]' => 30,
        'property[land_use][rowcrops_acreage]' => 40,
        'property[land_use][natural_acreage]' => 50,
        'property[land_use][other]' => 'Alpacas',
        'property[land_use][other_acreage]' => 60,
        'property[land_use][crop_types]' => 'Bananas',
      ],
      3 => [
        'interests[goals][goals][stewardship]' => TRUE,
        'interests[goals][goals][funding]' => TRUE,
        'interests[goals][goals][regulation]' => TRUE,
        'interests[goals][goals][economic]' => TRUE,
        'interests[goals][goals][other]' => TRUE,
        'interests[goals][other]' => 'I have big plans!',
        'interests[concerns][concerns][soil]' => TRUE,
        'interests[concerns][concerns][water]' => TRUE,
        'interests[concerns][concerns][animals]' => TRUE,
        'interests[concerns][concerns][plants]' => TRUE,
        'interests[concerns][concerns][air]' => TRUE,
        'interests[concerns][concerns][human]' => TRUE,
        'interests[concerns][concerns][energy]' => TRUE,
        'interests[concerns][concerns][other]' => TRUE,
        'interests[concerns][other]' => 'I have some concerns.',
        'interests[comments]' => 'I may have too many interests.',
      ],
    ];
    if (!is_null($step)) {
      return $field_data[$step];
    }
    return $field_data;
  }

  /**
   * Test intake form.
   */
  public function testIntakeForm() {

    // Log out the user.
    $this->drupalLogout();

    // Confirm that the form is available to anonymous users.
    $this->drupalGet('/intake');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Intake Form');

    // Log in the user.
    $this->drupalLogin($this->user);

    // Confirm that the form is available to the authenticated user.
    $this->drupalGet('/intake');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Intake Form');

    // Confirm that intro text is present.
    $this->assertSession()->pageTextContains('Please complete this form to express interest in working with your local Resource Conservation District.');

    // Confirm that the Next button is present, but not the Back button.
    $this->assertSession()->responseContains('value="Next"');
    $this->assertSession()->responseNotContains('value="Back"');

    // Press the Next button.
    $this->getSession()->getPage()->pressButton('Next');

    // Confirm that both the Next and Back buttons are present.
    $this->assertSession()->responseContains('value="Next"');
    $this->assertSession()->responseContains('value="Back"');

    // Confirm that the step 1 information is present.
    $this->assertSession()->pageTextContains('Stakeholder information');
    $this->assertSession()->pageTextContains('Step 1 of 3');
    $this->assertSession()->pageTextContains('25%');

    // Fill in all step 1 fields.
    foreach ($this->fieldData(1) as $id => $value) {
      $this->getSession()->getPage()->fillField($id, $value);
    }

    // Press the Back button.
    $this->getSession()->getPage()->pressButton('Back');

    // Confirm that intro text is present.
    $this->assertSession()->pageTextContains('Please complete this form to express interest in working with your local Resource Conservation District.');

    // Press the Next button.
    $this->getSession()->getPage()->pressButton('Next');

    // Confirm that all step 1 field values were preserved.
    foreach ($this->fieldData(1) as $id => $value) {
      $this->assertEquals($value, $this->getSession()->getPage()->findField($id)->getValue());
    }

    // Press the Next button.
    $this->getSession()->getPage()->pressButton('Next');

    // Confirm that the step 2 information is present.
    $this->assertSession()->pageTextContains('Property description');
    $this->assertSession()->pageTextContains('Step 2 of 3');
    $this->assertSession()->pageTextContains('50%');

    // Fill in all step 2 fields.
    foreach ($this->fieldData(2) as $id => $value) {
      $this->getSession()->getPage()->fillField($id, $value);
    }

    // Press the Back button.
    $this->getSession()->getPage()->pressButton('Back');

    // Confirm that we're back on step 1.
    $this->assertSession()->pageTextContains('Step 1');

    // Confirm that all step 1 field values were preserved.
    foreach ($this->fieldData(1) as $id => $value) {
      $this->assertEquals($value, $this->getSession()->getPage()->findField($id)->getValue());
    }

    // Press the Next button.
    $this->getSession()->getPage()->pressButton('Next');

    // Confirm that we're back on step 2.
    $this->assertSession()->pageTextContains('Step 2');

    // Confirm that all step 2 field values were preserved.
    foreach ($this->fieldData(2) as $id => $value) {
      $this->assertEquals($value, $this->getSession()->getPage()->findField($id)->getValue());
    }

    // Press the Next button.
    $this->getSession()->getPage()->pressButton('Next');

    // Confirm that the step 3 information is present.
    $this->assertSession()->pageTextContains('Stakeholder interests');
    $this->assertSession()->pageTextContains('Step 3 of 3');
    $this->assertSession()->pageTextContains('75%');

    // Fill in all step 3 fields.
    foreach ($this->fieldData(3) as $id => $value) {
      $this->getSession()->getPage()->fillField($id, $value);
    }

    // Press the Back button.
    $this->getSession()->getPage()->pressButton('Back');

    // Confirm that we're back on step 2.
    $this->assertSession()->pageTextContains('Step 2');

    // Confirm that all step 2 field values were preserved.
    foreach ($this->fieldData(2) as $id => $value) {
      $this->assertEquals($value, $this->getSession()->getPage()->findField($id)->getValue());
    }

    // Press the Next button.
    $this->getSession()->getPage()->pressButton('Next');

    // Confirm that we're back on step 3.
    $this->assertSession()->pageTextContains('Step 3');

    // Confirm that all step 3 field values were preserved.
    foreach ($this->fieldData(3) as $id => $value) {
      $this->assertEquals($value, $this->getSession()->getPage()->findField($id)->getValue());
    }

    // Press the Next button.
    $this->getSession()->getPage()->pressButton('Next');

    // Confirm that the review step information is present.
    $this->assertSession()->pageTextContains('Review');
    $this->assertSession()->pageTextContains('100%');

    // Confirm that some of the log data is previewed.
    $this->assertSession()->pageTextContains('Stakeholder name');
    $this->assertSession()->pageTextContains('Jane Doe');

    // Press the Back button.
    $this->getSession()->getPage()->pressButton('Back');

    // Confirm that we're back on step 3.
    $this->assertSession()->pageTextContains('Step 3');

    // Confirm that all step 3 field values were preserved.
    foreach ($this->fieldData(3) as $id => $value) {
      $this->assertEquals($value, $this->getSession()->getPage()->findField($id)->getValue());
    }

    // Press the Next button.
    $this->getSession()->getPage()->pressButton('Next');

    // Confirm that we're back on the review step.
    $this->assertSession()->pageTextContains('Review');

    // Submit the form.
    $this->getSession()->getPage()->pressButton('Submit');

    // Confirm that an intake log was created.
    $logs = \Drupal::entityTypeManager()->getStorage('log')->loadMultiple();
    $this->assertCount(1, $logs);
    /** @var \Drupal\log\Entity\LogInterface $log */
    $log = reset($logs);

    // Confirm that the log type is rcd_intake.
    $this->assertEquals('rcd_intake', $log->bundle());

    // Confirm that the log status is pending.
    $this->assertEquals('pending', $log->get('status')->value);

    // Confirm all the log fields were saved correctly.
    $field_data = $this->fieldData();
    $expected = [
      'intake_stakeholder_name' => $field_data[1]['stakeholder[personal][name]'],
      'intake_stakeholder_type' => $field_data[1]['stakeholder[personal][type]'],
      'intake_stakeholder_email' => $field_data[1]['stakeholder[personal][email]'],
      'intake_stakeholder_phone' => $field_data[1]['stakeholder[personal][phone]'],
      'intake_stakeholder_street' => $field_data[1]['stakeholder[personal][address][street]'],
      'intake_stakeholder_city' => $field_data[1]['stakeholder[personal][address][city]'],
      'intake_stakeholder_state' => $field_data[1]['stakeholder[personal][address][state]'],
      'intake_stakeholder_zip' => $field_data[1]['stakeholder[personal][address][zip]'],
      'intake_stakeholder_group' => array_keys(RcdOptionLists::stakeholderGroups()),
      'intake_stakeholder_group_other' => $field_data[1]['stakeholder[personal][group_other]'],
      'intake_farm_name' => $field_data[2]['property[info][farm_name]'],
      'intake_property_own_or_lease' => $field_data[2]['property[info][own_or_lease]'],
      'intake_property_owner' => 'My sister',
      'intake_property_lease_exp' => NULL,
      'intake_property_acreage' => $field_data[2]['property[info][acreage]'],
      'intake_property_street' => $field_data[2]['property[info][street]'],
      'intake_property_city' => $field_data[2]['property[info][city]'],
      'intake_property_state' => $field_data[2]['property[info][state]'],
      'intake_property_zip' => $field_data[2]['property[info][zip]'],
      'intake_property_parcel_gps' => '',
      'intake_property_use' => array_keys(RcdOptionLists::landUses()),
      'intake_property_use_grazing_ac' => $field_data[2]['property[land_use][grazing_acreage]'],
      'intake_property_use_vineyard_ac' => $field_data[2]['property[land_use][vineyards_acreage]'],
      'intake_property_use_orchard_ac' => $field_data[2]['property[land_use][orchards_acreage]'],
      'intake_property_use_rowcrop_ac' => $field_data[2]['property[land_use][rowcrops_acreage]'],
      'intake_property_use_natural_ac' => $field_data[2]['property[land_use][natural_acreage]'],
      'intake_property_use_other' => $field_data[2]['property[land_use][other]'],
      'intake_property_use_other_ac' => $field_data[2]['property[land_use][other_acreage]'],
      'intake_property_use_crop_types' => $field_data[2]['property[land_use][crop_types]'],
      'intake_goals' => array_keys(RcdOptionLists::goals()),
      'intake_goals_other' => $field_data[3]['interests[goals][other]'],
      'intake_concerns' => array_keys(RcdOptionLists::concerns()),
      'intake_concerns_other' => $field_data[3]['interests[concerns][other]'],
      'intake_comments' => $field_data[3]['interests[comments]'],
      'intake_rcd_sharing_allowed' => TRUE,
    ];
    foreach ($expected as $field => $value) {
      if (is_array($value)) {
        $field_values = $log->get($field)->getValue();
        $this->assertEquals(count($value), count($field_values));
        foreach ($field_values as $i => $field_value) {
          $this->assertEquals($value[$i], $field_value['value']);
        }
      }
      else {
        if (is_null($value)) {
          $this->assertTrue($log->get($field)->isEmpty());
        }
        else {
          $this->assertEquals($value, $log->get($field)->value);
        }
      }
    }

    // Test fields that were not covered by the first submission.
    $field_data = $this->fieldData();
    $field_data[1]['stakeholder[personal][share_rcds]'] = 'no';
    $field_data[2]['property[info][own_or_lease]'] = 'lease';
    $field_data[2]['property[info][lease_expiration]'] = '08/19/2025';
    $field_data[2]['property[info][has_address]'] = 'no';
    $field_data[2]['property[info][parcel_gps]'] = '1234567890';
    $this->drupalGet('/intake');
    $this->getSession()->getPage()->pressButton('Next');
    foreach ([1, 2, 3] as $step) {
      foreach ($field_data[$step] as $id => $value) {
        $this->getSession()->getPage()->fillField($id, $value);
      }
      $this->getSession()->getPage()->pressButton('Next');
    }
    $this->getSession()->getPage()->pressButton('Submit');
    $logs = \Drupal::entityTypeManager()->getStorage('log')->loadMultiple();
    $this->assertCount(2, $logs);
    /** @var \Drupal\log\Entity\LogInterface $log */
    $log = end($logs);
    $this->assertEquals(FALSE, $log->get('intake_rcd_sharing_allowed')->value);
    $this->assertEquals('lease', $log->get('intake_property_own_or_lease')->value);
    $this->assertEquals(1755525600, $log->get('intake_property_lease_exp')->value);
    $this->assertEquals('1234567890', $log->get('intake_property_parcel_gps')->value);
  }

}
