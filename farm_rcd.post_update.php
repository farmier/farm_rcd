<?php

/**
 * @file
 * Post update functions for farm_rcd module.
 */

declare(strict_types=1);

use Drupal\Core\Utility\UpdateException;
use Drupal\farm_map\Entity\MapBehavior;
use Drupal\farm_map\Entity\MapType;
use Drupal\symfony_mailer_lite\Entity\Transport;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Add acreage and linear feet measurements to practice implementation plans.
 */
function farm_rcd_post_update_practice_measurements(&$sandbox) {

  // Acreage.
  $options = [
    'type' => 'decimal',
    'label' => t('Acreage'),
    'description' => t('How many acres will this practice cover?'),
  ];
  $field_definition = \Drupal::service('farm_field.factory')->bundleFieldDefinition($options);
  \Drupal::entityDefinitionUpdateManager()->installFieldStorageDefinition('rcd_acres', 'plan', 'farm_rcd', $field_definition);

  // Linear feet.
  $options = [
    'type' => 'decimal',
    'label' => t('Linear feet'),
    'description' => t('How many linear feet will this practice cover?'),
  ];
  $field_definition = \Drupal::service('farm_field.factory')->bundleFieldDefinition($options);
  \Drupal::entityDefinitionUpdateManager()->installFieldStorageDefinition('rcd_linear_feet', 'plan', 'farm_rcd', $field_definition);
}

/**
 * Install and configure Symfony Mailer Lite.
 */
function farm_rcd_post_update_install_symfony_mailer_lite(&$sandbox) {

  // Install the Symfony Mailer Lite module.
  if (!\Drupal::service('module_handler')->moduleExists('symfony_mailer_lite')) {
    \Drupal::service('module_installer')->install(['symfony_mailer_lite']);
  }

  // Create the SMTP transport configuration entity.
  $transport = Transport::create([
    'id' => 'smtp',
    'label' => 'SMTP',
    'plugin' => 'smtp',
    'configuration' => [
      'user' => 'username',
      'pass' => 'password',
      'pass_key' => '',
      'use_key_module' => FALSE,
      'host' => 'smtp.example.com',
      'port' => 587,
      'query' => [
        'verify_peer' => TRUE,
        'local_domain' => '',
        'restart_threshold' => 100,
        'restart_threshold_sleep' => 0,
        'ping_threshold' => 100,
      ],
    ],
    'dependencies' => [
      'enforced' => [
        'module' => [
          'farm_rcd',
        ],
      ],
    ],
  ]);
  $transport->save();

  // Set Symfony Mailer Lite as the default mail system.
  \Drupal::configFactory()->getEditable('mailsystem.settings')->set('defaults', ['sender' => 'symfony_mailer_lite', 'formatter' => 'symfony_mailer_lite'])->save();

  // Set the default Symfony Mailer Lite transport to SMTP.
  \Drupal::configFactory()->getEditable('symfony_mailer_lite.settings')->set('default_transport', 'smtp')->save();
}

/**
 * Add rcp_document configuration to farm_rcd.mail.
 */
function farm_rcd_post_update_rcp_document(&$sandbox) {
  \Drupal::configFactory()->getEditable('farm_rcd.mail')->set('rcp_document', [
    'subject' => 'Resource Conservation Plan Documents',
    'body' => 'Your prepared Resource Conservation Plan document(s) are attached.',
  ])->save();
}

/**
 * Remove "not applicable" demographic option.
 */
function farm_rcd_post_update_na_demographic_option(&$sandbox) {
  \Drupal::database()->query("DELETE FROM log__intake_stakeholder_group WHERE intake_stakeholder_group_value = 'na'");
  \Drupal::database()->query("DELETE FROM log_revision__intake_stakeholder_group WHERE intake_stakeholder_group_value = 'na'");
}

/**
 * Add "other" demographic group field to intake log.
 */
function farm_rcd_post_update_intake_other_group(&$sandbox) {
  $options = [
    'type' => 'string',
    'label' => t('Stakeholder group (other)'),
  ];
  $field_definition = \Drupal::service('farm_field.factory')->bundleFieldDefinition($options);
  \Drupal::entityDefinitionUpdateManager()->installFieldStorageDefinition('intake_stakeholder_group_other', 'log', 'farm_rcd', $field_definition);
}

/**
 * Install the farm_form module.
 */
function farm_rcd_post_update_install_farm_form(&$sandbox = NULL) {
  if (!\Drupal::service('module_handler')->moduleExists('farm_form')) {
    \Drupal::service('module_installer')->install(['farm_form']);
  }
}

/**
 * Add a field for other practice names.
 */
function farm_rcd_post_update_other_practice_name(&$sandbox) {
  $options = [
    'type' => 'string',
    'label' => t('Other practice name'),
    'description' => t('If "Other" was selected for the practice, give the practice a name.'),
  ];
  $field_definition = \Drupal::service('farm_field.factory')->bundleFieldDefinition($options);
  \Drupal::entityDefinitionUpdateManager()->installFieldStorageDefinition('rcd_practice_other', 'plan', 'farm_rcd', $field_definition);
}

/**
 * Add Rangeland, Forestry, and Pasture land use types (remove Grazing).
 */
function farm_rcd_post_update_update_land_use_types(&$sandbox) {

  // Add intake_property_use_pasture_ac log field.
  $options = [
    'type' => 'decimal',
    'label' => t('Pasture land use acreage'),
    'min' => 0,
  ];
  $field_definition = \Drupal::service('farm_field.factory')->bundleFieldDefinition($options);
  \Drupal::entityDefinitionUpdateManager()->installFieldStorageDefinition('intake_property_use_pasture_ac', 'log', 'farm_rcd', $field_definition);

  // Add intake_property_use_forestry_ac log field.
  $options = [
    'type' => 'decimal',
    'label' => t('Forestry land use acreage'),
    'min' => 0,
  ];
  $field_definition = \Drupal::service('farm_field.factory')->bundleFieldDefinition($options);
  \Drupal::entityDefinitionUpdateManager()->installFieldStorageDefinition('intake_property_use_forestry_ac', 'log', 'farm_rcd', $field_definition);

  // Add intake_property_use_rangeland_ac log field.
  $options = [
    'type' => 'decimal',
    'label' => t('Rangeland land use acreage'),
    'min' => 0,
  ];
  $field_definition = \Drupal::service('farm_field.factory')->bundleFieldDefinition($options);
  \Drupal::entityDefinitionUpdateManager()->installFieldStorageDefinition('intake_property_use_rangeland_ac', 'log', 'farm_rcd', $field_definition);

  // Migrate intake_property_use "grazing" to "rangeland".
  \Drupal::database()->query("UPDATE log__intake_property_use SET intake_property_use_value = 'rangeland' WHERE intake_property_use_value = 'grazing'");
  \Drupal::database()->query("UPDATE log_revision__intake_property_use SET intake_property_use_value = 'rangeland' WHERE intake_property_use_value = 'grazing'");

  // Migrate values from intake_property_use_grazing_ac to
  // intake_property_use_rangeland_ac.
  \Drupal::database()->query("INSERT INTO log__intake_property_use_rangeland_ac (bundle, deleted, entity_id, revision_id, langcode, delta, intake_property_use_rangeland_ac_value) SELECT bundle, deleted, entity_id, revision_id, langcode, delta, intake_property_use_grazing_ac_value FROM log__intake_property_use_grazing_ac");
  \Drupal::database()->query("INSERT INTO log_revision__intake_property_use_rangeland_ac (bundle, deleted, entity_id, revision_id, langcode, delta, intake_property_use_rangeland_ac_value) SELECT bundle, deleted, entity_id, revision_id, langcode, delta, intake_property_use_grazing_ac_value FROM log_revision__intake_property_use_grazing_ac");

  // Remove intake_property_use_grazing_ac from log.
  $update_manager = \Drupal::entityDefinitionUpdateManager();
  $storage_definition = $update_manager->getFieldStorageDefinition('intake_property_use_grazing_ac', 'log');
  $update_manager->uninstallFieldStorageDefinition($storage_definition);
}

/**
 * Install the farm_comment_plan module.
 */
function farm_rcd_post_update_install_farm_comment_plan(&$sandbox = NULL) {
  if (!\Drupal::service('module_handler')->moduleExists('farm_comment_plan')) {
    \Drupal::service('module_installer')->install(['farm_comment_plan']);
  }
}

/**
 * Import farm_rcd_intake_plan View.
 */
function farm_rcd_post_update_create_farm_rcd_intake_plan(&$sandbox = NULL) {
  /** @var \Drupal\config_update\ConfigReverter $config_update */
  $config_update = \Drupal::service('config_update.config_update');
  $config_update->import('view', 'farm_rcd_intake_plan');
}

/**
 * Add zoom to property map behavior.
 */
function farm_rcd_post_update_map_zoom_to_property(&$sandbox) {

  // Create the RCD map type.
  $map_type = MapType::create([
    'id' => 'rcd',
    'label' => 'RCD map',
    'description' => 'Map for RCD module use cases.',
    'behaviors' => [],
    'options' => [],
    'dependencies' => [
      'enforced' => [
        'module' => [
          'farm_rcd',
        ],
      ],
    ],
  ]);
  $map_type->save();

  // Create the zoom behavior.
  $behavior = MapBehavior::create([
    'id' => 'rcd_property_zoom',
    'label' => 'Zoom to property',
    'description' => 'Zooms to a property.',
    'library' => 'farm_rcd/behavior_rcd_property_zoom',
    'settings' => [],
    'dependencies' => [
      'enforced' => [
        'module' => [
          'farm_rcd',
        ],
      ],
    ],
  ]);
  $behavior->save();
}

/**
 * Add practice implementation target start and end date fields.
 */
function farm_rcd_post_update_add_implementation_timeline(&$sandbox) {

  // Add practice implementation target start date.
  $options = [
    'type' => 'timestamp',
    'label' => t('Target start date'),
  ];
  $field_definition = \Drupal::service('farm_field.factory')->bundleFieldDefinition($options);
  \Drupal::entityDefinitionUpdateManager()->installFieldStorageDefinition('rcd_target_start_date', 'plan', 'farm_rcd', $field_definition);

  // Add practice implementation target end date.
  $options = [
    'type' => 'timestamp',
    'label' => t('Target end date'),
  ];
  $field_definition = \Drupal::service('farm_field.factory')->bundleFieldDefinition($options);
  \Drupal::entityDefinitionUpdateManager()->installFieldStorageDefinition('rcd_target_end_date', 'plan', 'farm_rcd', $field_definition);
}

/**
 * Install the farm_report module.
 */
function farm_rcd_post_update_install_farm_report(&$sandbox = NULL) {
  if (!\Drupal::service('module_handler')->moduleExists('farm_report')) {
    \Drupal::service('module_installer')->install(['farm_report']);
  }
}

/**
 * Add practice implementation geometry field.
 */
function farm_rcd_post_update_practice_implementation_geometry(&$sandbox) {
  $options = [
    'type' => 'geofield',
    'label' => t('Geometry'),
  ];
  $field_definition = \Drupal::service('farm_field.factory')->bundleFieldDefinition($options);
  \Drupal::entityDefinitionUpdateManager()->installFieldStorageDefinition('geometry', 'plan', 'farm_rcd', $field_definition);
}

/**
 * Add funding source taxonomy term vocabulary.
 */
function farm_rcd_post_update_create_funding_source_taxonomy(&$sandbox = NULL) {

  // This function will be run as a batch operation to save the new term
  // reference values on existing implementation plans. On the first run,
  // we will make preparations. This logic should only run once.
  if (!isset($sandbox['current_plan'])) {

    // Query the database for all funding source field data on plans.
    // Save it to $sandbox for future reference.
    $sandbox['plan_map'] = \Drupal::database()->query('SELECT entity_id, rcd_funding_source_value FROM {plan__rcd_funding_source} WHERE deleted = 0')->fetchAllKeyed();

    // Create the funding source vocabulary.
    $vid = 'rcd_funding_source';
    if (!Vocabulary::load($vid)) {
      $vocab = Vocabulary::create([
        'vid' => $vid,
        'name' => 'Funding source',
      ]);
      $vocab->save();
    }

    // Create taxonomy terms for each of the funding sources.
    // Add them to a term map in $sandbox for future reference.
    $unique_sources = array_unique($sandbox['plan_map']);
    $sandbox['term_map'] = [];
    foreach ($unique_sources as $source) {
      $term = Term::create(['vid' => 'rcd_funding_source', 'name' => $source]);
      $term->save();
      $sandbox['term_map'][$source] = $term->id();
    }

    // Get the Drupal entity definition update manager.
    $update_manager = \Drupal::entityDefinitionUpdateManager();

    // Delete the old funding source field.
    $storage_definition = $update_manager->getFieldStorageDefinition('rcd_funding_source', 'plan');
    $update_manager->uninstallFieldStorageDefinition($storage_definition);

    // Install the new funding source field.
    $options = [
      'type' => 'entity_reference',
      'label' => t('Funding source'),
      'description' => t('Describe where the funding for this practice came from.'),
      'target_type' => 'taxonomy_term',
      'target_bundle' => 'rcd_funding_source',
      'auto_create' => TRUE,
      'weight' => [
        'form' => -40,
        'view' => -40,
      ],
    ];
    $field_definition = \Drupal::service('farm_field.factory')->bundleFieldDefinition($options);
    $update_manager->installFieldStorageDefinition('rcd_funding_source', 'plan', 'farm_rcd', $field_definition);

    // If there are no implementation plans with funding source field
    // values, bail.
    if (empty($sandbox['plan_map'])) {
      return NULL;
    }

    // Track progress.
    $sandbox['current_plan'] = 0;
    $sandbox['#finished'] = 0;
  }

  // Iterate over plans, 10 at a time.
  $plan_ids = array_keys($sandbox['plan_map']);
  $plan_count = count($plan_ids);
  $end_plan = $sandbox['current_plan'] + 10;
  $end_plan = $end_plan > $plan_count ? $plan_count : $end_plan;
  for ($i = $sandbox['current_plan']; $i < $end_plan; $i++) {

    // Iterate the global counter.
    $sandbox['current_plan']++;

    // Get the plan ID.
    $id = $plan_ids[$i];

    // If there is no taxonomy term to assign, skip.
    if (empty($sandbox['plan_map'][$id]) || empty($sandbox['term_map'][$sandbox['plan_map'][$id]])) {
      continue;
    }

    // Load the plan.
    $plan = \Drupal::service('entity_type.manager')->getStorage('plan')->load($id);

    // If the plan didn't load, throw an update exception.
    if (empty($plan)) {
      throw new UpdateException('Could not load plan. ID: ' . $id);
    }

    // Assign the new funding source taxonomy term.
    $plan->set('rcd_funding_source', $sandbox['term_map'][$sandbox['plan_map'][$id]]);

    // Save a new revision of the plan.
    $plan->setNewRevision(TRUE);
    $plan->setRevisionLogMessage(t('Automatically migrated funding source to taxonomy term in the new Funding Source vocabulary.')->render());
    $plan->save();

    // Declare that the plan has been fixed.
    \Drupal::logger('farm_rcd')->notice(t('Plan @id funding source has been migrated.', ['@id' => $id]));
  }

  // Update progress.
  $sandbox['#finished'] = $sandbox['current_plan'] / count($sandbox['plan_map']);
  return NULL;
}
