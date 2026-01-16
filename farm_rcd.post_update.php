<?php

/**
 * @file
 * Post update functions for farm_rcd module.
 */

declare(strict_types=1);

use Drupal\symfony_mailer_lite\Entity\Transport;

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
