<?php

declare(strict_types=1);

namespace Drupal\farm_rcd\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\log\Entity\LogInterface;
use Drupal\plan\Entity\PlanInterface;

/**
 * Site assessments form.
 */
class SiteAssessmentsForm extends PlanningWorkflowFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'farm_rcd_site_assessment_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?PlanInterface $plan = NULL) {
    $form = parent::buildForm($form, $form_state, $plan);

    // Form details.
    $form['assessments'] = [
      '#type' => 'details',
      '#title' => $this->t('Site Assessments'),
      '#description' => $this->t('Site assessments are used to collect more information about land use areas during a site visit.'),
    ];

    // Require that a property with land asset children is associated with the
    // plan first.
    if (empty($this->landAssets)) {
      $form['assessments']['#markup'] = $this->t('Land use areas must be created before site assessments can be made.');
      return $form;
    }

    // Open if the status is "planning" and there are no site assessment logs.
    else {
      $form['assessments']['#open'] = $this->plan->get('status')->value == 'planning' && empty($this->siteAssessmentLogs);
    }

    // Do not open if the "open" query parameter is set, unless it is set to
    // "assessments".
    // @todo https://github.com/farmier/farm_rcd/issues/57
    if ($this->getRequest()->query->has('open')) {
      $form['assessments']['#open'] = FALSE;
      if ($this->getRequest()->query->get('open') == 'assessments') {
        $form['assessments']['#open'] = TRUE;
      }
    }

    // Build vertical tabs for each site assessment form.
    $form['assessments']['tabs'] = [
      '#type' => 'vertical_tabs',
    ];

    // Provide simplified edit forms for site assessment logs.
    foreach ($this->siteAssessmentLogs as $id => $log) {

      // Details wrapper.
      $form['assessments'][$id] = $this->buildSiteAssessmentForm($log);
      $form['assessments'][$id]['#type'] = 'details';
      $form['assessments'][$id]['#title'] = $log->label();
      $form['assessments'][$id]['#description'] = $this->t('Site assessment log: <a href=":uri">%label</a>', [':uri' => $log->toUrl()->toString(), '%label' => $log->label()]);
      $form['assessments'][$id]['#group'] = 'assessments][tabs';
    }

    // Add a new site assessment.
    $form['assessments']['add'] = $this->buildSiteAssessmentForm();
    $form['assessments']['add']['#type'] = 'details';
    $form['assessments']['add']['#title'] = $this->t('+ Add site assessment');
    $form['assessments']['add']['#description'] = $this->t('Create a new site assessment log to record information about a land use area.');

    // If there are site assessment logs, show the add form in vertical tabs.
    // Otherwise, leave it ungrouped and open it by default.
    if (!empty($this->siteAssessmentLogs)) {
      $form['assessments']['add']['#group'] = 'assessments][tabs';
    }
    else {
      $form['assessments']['add']['#open'] = TRUE;
    }

    // Submit button.
    $form['assessments']['actions'] = [
      '#type' => 'actions',
      '#weight' => 1000,
    ];
    $form['assessments']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save site assessments'),
    ];

    return $form;
  }

  /**
   * Site assessment log subform.
   *
   * @param \Drupal\log\Entity\LogInterface|null $log
   *   The log entity or NULL.
   *
   * @return array
   *   The render array defining the elements of the form.
   */
  protected function buildSiteAssessmentForm(?LogInterface $log = NULL) {

    // Log ID (if available).
    $form['log_id'] = [
      '#type' => 'value',
      '#value' => !is_null($log) ? $log->id() : NULL,
    ];

    // Location reference.
    // If this is an existing log, show links to land assets, but do not allow
    // editing.
    if (!is_null($log)) {
      /** @var \Drupal\asset\Entity\AssetInterface[] $locations */
      $locations = $log->get('location')->referencedEntities();
      $form['location'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Land use area'),
        '#options' => array_combine(
          array_map(function ($asset) {
            return $asset->id();
          }, $locations),
          array_map(function ($asset) {
            return $asset->toLink()->toString();
          }, $locations),
        ),
        '#default_value' => array_map(function ($asset) {
          return $asset->id();
        }, $locations),
        '#required' => TRUE,
        '#disabled' => TRUE,
      ];
    }
    else {
      $form['location'] = [
        '#type' => 'select',
        '#title' => $this->t('Land use area'),
        '#options' => array_combine(
          array_keys($this->landAssets),
          array_map(function ($asset) {
            return $asset->toLink()->toString();
          }, $this->landAssets),
        ),
      ];

      // Add a null option to the beginning and default to that.
      // Drupal core only adds this if the field is required and doesn't have a
      // null default value. We do this to ensure that the form can be submitted
      // without requiring the new location details. See #states below.
      $form['location']['#options'] = [NULL => '- Select -'] + $form['location']['#options'];
      $form['location']['#default_value'] = NULL;
    }

    // Build the name of the location field for #states below.
    $location_name = !is_null($log) ? 'assessments[' . $log->id() . '][location]' : 'assessments[add][location]';

    // Date of site visit.
    $form['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Date of site visit'),
      '#date_date_element' => 'date',
      '#date_time_element' => 'none',
      '#default_value' => date('Y-m-d', $log ? (int) $log->get('timestamp')->value : NULL),
      '#states' => [
        'required' => [
          ':input[name="' . $location_name . '"]' => ['filled' => TRUE],
        ],
      ],
    ];

    // Land use history.
    $form['land_use_history'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Land use history'),
      '#default_value' => $log ? $log->get('rcd_land_use_history')->value : '',
    ];

    // Existing infrastructure.
    $form['infrastructure'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Existing infrastructure'),
      '#default_value' => $log ? $log->get('rcd_infrastructure')->value : '',
    ];

    // Priority environmental concerns.
    $form['priority_concerns'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Priority environmental concerns'),
      '#default_value' => $log ? $log->get('rcd_priority_concerns')->value : '',
    ];

    // Watersheds.
    $form['watershed'] = [
      '#type' => 'entity_autocomplete_tagify',
      '#title' => $this->t('Watersheds'),
      '#target_type' => 'taxonomy_term',
      '#selection_handler' => 'default:taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => ['rcd_watershed'],
        'sort' => [
          'field' => 'name',
          'direction' => 'asc',
        ],
      ],
      '#autocreate' => TRUE,
      // @see https://www.drupal.org/project/tagify/issues/3551805
      '#attributes' => [
        'class' => ['tagify--autocreate'],
      ],
      '#default_value' => $log ? $log->get('rcd_watershed')->referencedEntities() : NULL,
    ];

    // Groundwater basins.
    $form['groundwater_basin'] = [
      '#type' => 'entity_autocomplete_tagify',
      '#title' => $this->t('Groundwater basins'),
      '#target_type' => 'taxonomy_term',
      '#selection_handler' => 'default:taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => ['rcd_groundwater_basin'],
        'sort' => [
          'field' => 'name',
          'direction' => 'asc',
        ],
      ],
      '#autocreate' => TRUE,
      // @see https://www.drupal.org/project/tagify/issues/3551805
      '#attributes' => [
        'class' => ['tagify--autocreate'],
      ],
      '#default_value' => $log ? $log->get('rcd_groundwater_basin')->referencedEntities() : NULL,
    ];

    // Wildlife species.
    $form['wildlife_species'] = [
      '#type' => 'entity_autocomplete_tagify',
      '#title' => $this->t('Wildlife species'),
      '#target_type' => 'taxonomy_term',
      '#selection_handler' => 'default:taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => ['rcd_wildlife_species'],
        'sort' => [
          'field' => 'name',
          'direction' => 'asc',
        ],
      ],
      '#autocreate' => TRUE,
      // @see https://www.drupal.org/project/tagify/issues/3551805
      '#attributes' => [
        'class' => ['tagify--autocreate'],
      ],
      '#default_value' => $log ? $log->get('rcd_wildlife_species')->referencedEntities() : NULL,
    ];

    // Plant species.
    $form['plant_species'] = [
      '#type' => 'entity_autocomplete_tagify',
      '#title' => $this->t('Plant species'),
      '#target_type' => 'taxonomy_term',
      '#selection_handler' => 'default:taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => ['rcd_plant_species'],
        'sort' => [
          'field' => 'name',
          'direction' => 'asc',
        ],
      ],
      '#autocreate' => TRUE,
      // @see https://www.drupal.org/project/tagify/issues/3551805
      '#attributes' => [
        'class' => ['tagify--autocreate'],
      ],
      '#default_value' => $log ? $log->get('rcd_plant_species')->referencedEntities() : NULL,
    ];

    // Landowner objectives.
    $form['landowner_objectives'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Landowner objectives'),
      '#default_value' => $log ? $log->get('rcd_landowner_objectives')->value : '',
    ];

    // Notes.
    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notes'),
      '#default_value' => $log ? $log->get('notes')->value : '',
    ];

    // Build vertical tabs for each resource assessment.
    $form['resources'] = [
      '#type' => 'vertical_tabs',
    ];

    // Build fields for each resource assessment.
    $resources = [
      'soil' => [
        'title' => $this->t('Soil'),
        'description' => $this->t('Describe soil health, compaction, drought resilience, structure, erosion, nutrient management, etc.'),
      ],
      'water' => [
        'title' => $this->t('Water'),
        'description' => $this->t('Describe water quality issues, supply, flooding, drainage concerns, etc.'),
      ],
      'plant' => [
        'title' => $this->t('Plant/Vegetation'),
        'description' => $this->t('Describe plant/vegetation yield, quality, vigor, invasives, new crop introduction, nutrient management, etc.'),
      ],
      'aquatic' => [
        'title' => $this->t('Aquatic Habitat'),
        'description' => $this->t('Describe aquatic habitat drainage, riparian vegetation, erosion, sedimentation, etc.'),
      ],
      'livestock' => [
        'title' => $this->t('Livestock'),
        'description' => $this->t('Describe livestock health, protection, forage, etc.'),
      ],
      'wildlife' => [
        'title' => $this->t('Wildlife'),
        'description' => $this->t('Describe wildlife and pollinator habitat, IPM and/or pest management strategies, crop protection, predation issues, etc.'),
      ],
      'infrastructure' => [
        'title' => $this->t('Infrastructure'),
        'description' => $this->t('Describe fencing, water supply and distribution, roads, drainage, etc.'),
      ],
    ];
    foreach ($resources as $key => $resource) {

      // Details wrapper.
      $log_id = !is_null($log) ? $log->id() : 'add';
      $form['resources'][$key] = [
        '#type' => 'details',
        '#title' => $resource['title'],
        '#description' => $resource['description'],
        '#group' => 'assessments][' . $log_id . '][resources',
      ];

      // Rating.
      $form['resources'][$key]['rating'] = [
        '#type' => 'select',
        '#title' => $this->t('Rating'),
        '#description' => $this->t('Please rate the condition of this resource between 1-5, where 1 is terrible and 5 is excellent.'),
        '#options' => [
          NULL => '',
          1 => 1,
          2 => 2,
          3 => 3,
          4 => 4,
          5 => 5,
        ],
        '#default_value' => $log ? $log->get('rcd_' . $key . '_rating')->value : NULL,
      ];

      // Baseline conditions.
      $form['resources'][$key]['baseline'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Baseline conditions'),
        '#default_value' => $log ? $log->get('rcd_' . $key . '_baseline')->value : '',
      ];

      // Goals.
      $form['resources'][$key]['goals'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Goals'),
        '#default_value' => $log ? $log->get('rcd_' . $key . '_goals')->value : '',
      ];

      // How to achieve resource goals.
      $form['resources'][$key]['strategy'] = [
        '#type' => 'textarea',
        '#title' => $this->t('How to achieve resource goals'),
        '#default_value' => $log ? $log->get('rcd_' . $key . '_strategy')->value : '',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Filter submitted values to those with a numeric key (representing the
    // site assessment log ID), or "add" (for adding a new practice plan).
    $assessment_values = array_filter($form_state->getValue('assessments'), function ($key) {
      return is_numeric($key) || $key === 'add';
    }, ARRAY_FILTER_USE_KEY);

    // If the "add" location field is empty, a new site assessment log will not
    // be created.
    if (empty($assessment_values['add']['location'])) {
      unset($assessment_values['add']);
    }

    // For each set of values, generate/update and validate the site assessment
    // log. The generateSiteAssessmentLog() method will return NULL if nothing
    // has changed on an existing log, so we skip those.
    $logs = [];
    foreach ($assessment_values as $values) {
      $log = $this->generateSiteAssessmentLog($values);
      if (is_null($log)) {
        continue;
      }
      $violations = $log->validate();
      if ($violations->count() > 0) {
        $form_state->setErrorByName('', $this->t('The site assessment log did not pass validation.'));
        foreach ($violations as $violation) {
          $this->messenger()->addWarning($violation->getMessage());
        }
        return;
      }
      $logs[] = $log;
    }

    // Save the logs to form state storage.
    $form_state->setStorage(['logs' => $logs]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Load the site assessment logs from storage.
    // Only new or modified logs will be included.
    $storage = $form_state->getStorage();
    $logs = $storage['logs'] ?? [];

    // If there are no logs, bail.
    if (empty($logs)) {
      $this->messenger()->addWarning($this->t('No site assessment logs saved.'));
      return;
    }

    // Save the site assessment logs with revision log messages that reference
    // the plan. Keep track of logs that were created or updated so that we can
    // build a revision log message for the plan.
    $created_logs = [];
    $updated_logs = [];
    foreach ($logs as $log) {
      if ($log->isNew()) {
        $log_revision = 'Created via <a href=":plan_uri">@plan_label</a>.';
        $created_logs[] = $log;
      }
      else {
        $log_revision = 'Updated via <a href=":plan_uri">@plan_label</a>.';
        $updated_logs[] = $log;
      }
      $plan_args = [
        ':plan_uri' => $this->plan->toUrl()->toString(),
        '@plan_label' => $this->plan->label(),
      ];
      $log->setRevisionLogMessage((string) new FormattableMarkup($log_revision, $plan_args));
      $log->save();
    }

    // Build and save a revision log message to the plan.
    $plan_revisions = [];
    if (!empty($created_logs)) {
      $log_links = array_map(function (LogInterface $log) {
        return (string) new FormattableMarkup('<a href=":uri">@label</a>', [':uri' => $log->toUrl()->toString(), '@label' => $log->label()]);
      }, $created_logs);
      $plan_revisions[] = 'Created site assessment log' . (count($log_links) > 1 ? 's' : '') . ': ' . implode(', ', $log_links) . '.';
    }
    if (!empty($updated_logs)) {
      $log_links = array_map(function (LogInterface $log) {
        return (string) new FormattableMarkup('<a href=":uri">@label</a>', [':uri' => $log->toUrl()->toString(), '@label' => $log->label()]);
      }, $updated_logs);
      $plan_revisions[] = 'Updated site assessment log' . (count($log_links) > 1 ? 's' : '') . ': ' . implode(', ', $log_links) . '.';
    }
    $this->plan->setRevisionLogMessage(implode(' ', $plan_revisions));
    $this->plan->save();

    // Show a message.
    $this->messenger()->addMessage($this->t('Site assessment logs saved.'));

    // If new logs were created, set a query parameter to keep the form open.
    // @todo https://github.com/farmier/farm_rcd/issues/57
    if (!empty($created_logs)) {
      $form_state->setRedirectUrl($this->plan->toUrl()->setOption('query', ['open' => 'assessments']));
    }
  }

  /**
   * Generate/update a site assessment log entity from submitted values.
   *
   * @param array $values
   *   Submitted values from $form_state->getValue().
   *
   * @return \Drupal\log\Entity\LogInterface|null
   *   Returns an unsaved site assessment log entity, or null if the log
   *   already exists and nothing is changing on it.
   */
  protected function generateSiteAssessmentLog(array $values): ?LogInterface {

    // If a log ID is included, load it.
    // Otherwise, start a new one.
    $log_storage = $this->entityTypeManager->getStorage('log');
    if (!empty($values['log_id'])) {
      /** @var \Drupal\log\Entity\LogInterface $log */
      $log = $log_storage->load($values['log_id']);
    }
    else {
      $asset_storage = $this->entityTypeManager->getStorage('asset');
      $land = $asset_storage->load($values['location']);
      /** @var \Drupal\log\Entity\LogInterface $log */
      $log = $log_storage->create([
        'type' => 'rcd_site_assessment',
        'location' => [$land],
        'status' => 'done',
      ]);
    }

    // Keep track of whether the log is changed. New logs always are.
    $changed = $log->isNew();

    // Fill in the log details from form values, if they have changed.
    $field_values = [
      'timestamp' => strtotime($values['date']),
      'notes' => $values['notes'],
      'rcd_land_use_history' => $values['land_use_history'],
      'rcd_infrastructure' => $values['infrastructure'],
      'rcd_priority_concerns' => $values['priority_concerns'],
      'rcd_landowner_objectives' => $values['landowner_objectives'],
    ];
    foreach ($field_values as $field => $value) {
      if ($log->get($field)->value != $value) {
        $log->set($field, $value);
        $changed = TRUE;
      }
    }

    // Process resource field sets.
    foreach ([
      'soil',
      'water',
      'plant',
      'aquatic',
      'livestock',
      'wildlife',
      'infrastructure',
    ] as $resource) {
      foreach (['rating', 'baseline', 'goals', 'strategy'] as $resource_field) {
        $field = 'rcd_' . $resource . '_' . $resource_field;
        $value = $values['resources'][$resource][$resource_field];
        if ($log->get($field)->value != $value) {
          $log->set($field, $value);
          $changed = TRUE;
        }
      }
    }

    // Process taxonomy reference fields.
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    foreach ([
      'watershed',
      'groundwater_basin',
      'wildlife_species',
      'plant_species',
    ] as $name) {
      $vid = 'rcd_' . $name;
      $existing_ids = array_map(function ($term) {
        return $term->id();
      }, $log->get($vid)->referencedEntities());
      $updated_terms = [];
      $updated_ids = [];
      if (!empty($values[$name])) {
        $updated_terms = array_map(function ($value) use ($term_storage, $vid) {
          if (!empty($value['entity_id'])) {
            return $term_storage->load($value['entity_id']);
          }
          elseif (!empty($value['value'])) {
            return $this->createOrLoadTerm($value['value'], $vid);
          }
          return NULL;
        }, json_decode($values[$name], TRUE) ?? []);
        $updated_ids = array_map(function ($term) {
          return $term->id();
        }, $updated_terms);
      }
      if (!(empty(array_diff($existing_ids, $updated_ids)) && empty(array_diff($updated_ids, $existing_ids)))) {
        $log->set($vid, []);
        $log->set($vid, $updated_terms);
        $changed = TRUE;
      }
    }

    // Set the name of the log based on its timestamp and locations.
    // Only add the first location name. If there are multiple locations, add
    // "(+ X more)".
    // Ensure the name is under 255 characters (we need to do this because the
    // user can't).
    $name = date('m/d/Y', (int) $log->get('timestamp')->value);
    $locations = array_map(function ($location) {
      /** @var \Drupal\asset\Entity\AssetInterface $location */
      return $location->label();
    }, $log->get('location')->referencedEntities());
    $name .= ' ' . $locations[0];
    $count = count($locations);
    if ($count > 1) {
      $name .= ' (+ ' . ($count - 1) . ' more)';
    }
    $name = mb_strimwidth($name, 0, 255, '…');
    if ($log->get('name')->value != $name) {
      $log->set('name', $name);
      $changed = TRUE;
    }

    // If the log has changed, return it.
    // Otherwise, return NULL.
    if ($changed) {
      return $log;
    }
    return NULL;
  }

}
