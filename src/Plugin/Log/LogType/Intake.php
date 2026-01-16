<?php

declare(strict_types=1);

namespace Drupal\farm_rcd\Plugin\Log\LogType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\farm_entity\Attribute\LogType;
use Drupal\farm_entity\Plugin\Log\LogType\FarmLogType;
use Drupal\farm_rcd\RcdOptionLists;

/**
 * Provides the RCD Intake log type.
 */
#[LogType(
  id: 'rcd_intake',
  label: new TranslatableMarkup('Intake'),
)]
class Intake extends FarmLogType {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = [];
    $field_info = [

      // Stakeholder information.
      'intake_stakeholder_name' => [
        'type' => 'string',
        'label' => $this->t('Stakeholder name'),
      ],
      'intake_stakeholder_email' => [
        'type' => 'email',
        'label' => $this->t('Stakeholder email'),
      ],
      'intake_stakeholder_phone' => [
        'type' => 'string',
        'label' => $this->t('Stakeholder phone'),
      ],
      'intake_stakeholder_street' => [
        'type' => 'string',
        'label' => $this->t('Stakeholder street'),
      ],
      'intake_stakeholder_city' => [
        'type' => 'string',
        'label' => $this->t('Stakeholder city'),
      ],
      'intake_stakeholder_state' => [
        'type' => 'list_string',
        'label' => $this->t('Stakeholder state'),
        'allowed_values' => RcdOptionLists::states(),
      ],
      'intake_stakeholder_zip' => [
        'type' => 'string',
        'label' => $this->t('Stakeholder zip'),
      ],
      'intake_stakeholder_type' => [
        'type' => 'list_string',
        'label' => $this->t('Stakeholder type'),
        'allowed_values' => RcdOptionLists::stakeholderTypes(),
      ],
      'intake_stakeholder_group' => [
        'type' => 'list_string',
        'label' => $this->t('Stakeholder group'),
        'allowed_values' => RcdOptionLists::stakeholderGroups(),
        'multiple' => TRUE,
      ],
      'intake_stakeholder_group_other' => [
        'type' => 'string',
        'label' => $this->t('Stakeholder group (other)'),
      ],

      // Property information.
      'intake_farm_name' => [
        'type' => 'string',
        'label' => $this->t('Property name'),
      ],
      'intake_property_own_or_lease' => [
        'type' => 'list_string',
        'label' => $this->t('Own or lease'),
        'allowed_values' => [
          'own' => $this->t('Own'),
          'lease' => $this->t('Lease'),
          'unknown' => $this->t('Unknown'),
        ],
      ],
      'intake_property_owner' => [
        'type' => 'string',
        'label' => $this->t('Property owner'),
      ],
      'intake_property_lease_exp' => [
        'type' => 'timestamp',
        'label' => $this->t('Lease expiration'),
      ],
      'intake_property_acreage' => [
        'type' => 'decimal',
        'label' => $this->t('Property acreage'),
        'min' => 0,
      ],
      'intake_property_street' => [
        'type' => 'string',
        'label' => $this->t('Property street'),
      ],
      'intake_property_city' => [
        'type' => 'string',
        'label' => $this->t('Property city'),
      ],
      'intake_property_state' => [
        'type' => 'list_string',
        'label' => $this->t('Property state'),
        'allowed_values' => RcdOptionLists::states(),
      ],
      'intake_property_zip' => [
        'type' => 'string',
        'label' => $this->t('Property zip'),
      ],
      'intake_property_parcel_gps' => [
        'type' => 'string',
        'label' => $this->t('Parcel number or GPS coordinates'),
      ],
      'intake_property_use' => [
        'type' => 'list_string',
        'label' => $this->t('Land use'),
        'allowed_values' => RcdOptionLists::landUses(),
        'multiple' => TRUE,
      ],
      'intake_property_use_grazing_ac' => [
        'type' => 'decimal',
        'label' => $this->t('Grazing land use acreage'),
        'min' => 0,
      ],
      'intake_property_use_vineyard_ac' => [
        'type' => 'decimal',
        'label' => $this->t('Vineyards land use acreage'),
        'min' => 0,
      ],
      'intake_property_use_orchard_ac' => [
        'type' => 'decimal',
        'label' => $this->t('Orchards land use acreage'),
        'min' => 0,
      ],
      'intake_property_use_rowcrop_ac' => [
        'type' => 'decimal',
        'label' => $this->t('Row crops land use acreage'),
        'min' => 0,
      ],
      'intake_property_use_natural_ac' => [
        'type' => 'decimal',
        'label' => $this->t('Natural land use acreage'),
        'min' => 0,
      ],
      'intake_property_use_other' => [
        'type' => 'string',
        'label' => $this->t('Other land use'),
      ],
      'intake_property_use_other_ac' => [
        'type' => 'decimal',
        'label' => $this->t('Other land use acreage'),
        'min' => 0,
      ],
      'intake_property_use_crop_types' => [
        'type' => 'string',
        'label' => $this->t('Crop types'),
      ],

      // Goals.
      'intake_goals' => [
        'type' => 'list_string',
        'label' => $this->t('Goals'),
        'allowed_values' => RcdOptionLists::goals(),
        'multiple' => TRUE,
      ],
      'intake_goals_other' => [
        'type' => 'string',
        'label' => $this->t('Other goals'),
      ],

      // Concerns.
      'intake_concerns' => [
        'type' => 'list_string',
        'label' => $this->t('Concerns'),
        'allowed_values' => RcdOptionLists::concerns(),
        'multiple' => TRUE,
      ],
      'intake_concerns_other' => [
        'type' => 'string',
        'label' => $this->t('Other concerns'),
      ],

      // Additional comments.
      'intake_comments' => [
        'type' => 'string_long',
        'label' => $this->t('Additional comments'),
      ],

      // Allow sharing with other RCDs.
      'intake_rcd_sharing_allowed' => [
        'type' => 'boolean',
        'label' => $this->t('Allow sharing the application information with other RCDs'),
      ],
    ];
    foreach ($field_info as $name => $info) {
      $fields[$name] = $this->farmFieldFactory->bundleFieldDefinition($info);
    }
    return $fields;
  }

}
