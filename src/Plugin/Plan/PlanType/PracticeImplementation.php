<?php

declare(strict_types=1);

namespace Drupal\farm_rcd\Plugin\Plan\PlanType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\farm_entity\Attribute\PlanType;
use Drupal\farm_entity\Plugin\Plan\PlanType\FarmPlanType;
use Drupal\farm_rcd\ConservationPractices;

/**
 * Provides the RCD practice implementation plan type.
 */
#[PlanType(
  id: 'rcd_practice_implementation',
  label: new TranslatableMarkup('Practice implementation plan'),
)]
class PracticeImplementation extends FarmPlanType {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {

    // Inherit the default asset and log reference fields.
    $fields = parent::buildFieldDefinitions();

    // Add additional fields.
    $field_info = [

      // Farm organization entity.
      'farm' => [
        'type' => 'entity_reference',
        'label' => $this->t('Farm'),
        'description' => $this->t('Associates the practice implementation plan with a farm organization.'),
        'target_type' => 'organization' ,
        'target_bundle' => 'farm',
        'required' => TRUE,
      ],

      // Land asset.
      'land' => [
        'type' => 'entity_reference',
        'label' => $this->t('Land asset'),
        'description' => $this->t('Associates the practice implementation plan with a land asset.'),
        'target_type' => 'asset',
        'target_bundle' => 'land',
        'required' => TRUE,
      ],

      // Geometry.
      'geometry' => [
        'type' => 'geofield',
        'label' => $this->t('Geometry'),
      ],

      // Conservation practice.
      'rcd_practice' => [
        'type' => 'list_string',
        'label' => $this->t('Conservation practice'),
        'description' => $this->t('Specify the conservation practice that this plan intends to implement.'),
        'allowed_values' => array_map(function ($practice) {
          $label = $practice['label']->render();
          if (!empty($practice['nrcs_code'])) {
            $label .= ' (NRCS code ' . $practice['nrcs_code'] . ')';
          }
          return $label;
        }, ConservationPractices::definitions()),
        'required' => TRUE,
      ],

      // Other practice name.
      'rcd_practice_other' => [
        'type' => 'string',
        'label' => $this->t('Other practice name'),
        'description' => $this->t('If "Other" was selected for the practice, give the practice a name.'),
      ],

      // Acreage.
      'rcd_acres' => [
        'type' => 'decimal',
        'label' => $this->t('Acreage'),
        'description' => $this->t('How many acres will this practice cover?'),
      ],

      // Linear feet.
      'rcd_linear_feet' => [
        'type' => 'decimal',
        'label' => $this->t('Linear feet'),
        'description' => $this->t('How many linear feet will this practice cover?'),
      ],

      // Start date.
      'rcd_target_start_date' => [
        'type' => 'timestamp',
        'label' => $this->t('Target start date'),
        'description' => $this->t('The intended start date for this practice implementation.'),
      ],

      // End date.
      'rcd_target_end_date' => [
        'type' => 'timestamp',
        'label' => $this->t('Target end date'),
        'description' => $this->t('The intended end date for this practice implementation.'),
      ],

      // Funding source.
      'rcd_funding_source' => [
        'type' => 'string',
        'label' => $this->t('Funding source'),
        'description' => $this->t('Describe where the funding for this practice came from.'),
      ],

    ];
    foreach ($field_info as $name => $info) {
      $fields[$name] = $this->farmFieldFactory->bundleFieldDefinition($info);
    }

    // Change the rcd_practice form widget to a dropdown.
    $options = $fields['rcd_practice']->getDisplayOptions('form');
    $options['type'] = 'options_select';
    $fields['rcd_practice']->setDisplayOptions('form', $options);

    // Make farm and land references immutable.
    $fields['farm']->addConstraint('RcdImmutable');
    $fields['land']->addConstraint('RcdImmutable');

    // Add practice measurement constraints to rcd_acres and rcd_linear_feet.
    $fields['rcd_acres']->addConstraint('PracticeMeasurement');
    $fields['rcd_linear_feet']->addConstraint('PracticeMeasurement');

    return $fields;
  }

}
