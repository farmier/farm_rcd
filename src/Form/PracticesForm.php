<?php

declare(strict_types=1);

namespace Drupal\farm_rcd\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\farm_rcd\ConservationPractices;
use Drupal\plan\Entity\PlanInterface;

/**
 * Practices form.
 */
class PracticesForm extends PlanningWorkflowFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'farm_rcd_practice_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?PlanInterface $plan = NULL) {
    $form = parent::buildForm($form, $form_state, $plan);

    $form['practices'] = [
      '#type' => 'details',
      '#title' => $this->t('Conservation Practices'),
      '#description' => $this->t('Propose conservation practices for each land use area. A Practice Implementation Plan will be created for each practice with its own status and logs for tracking implementation details.'),
    ];

    // Require that a property with land asset children is associated with the
    // plan first.
    if (empty($this->landAssets)) {
      $form['practices']['#markup'] = $this->t('Land use areas must be created before conservations practices can be added.');
      return $form;
    }

    // Open if the status is "planning" and there are no practice
    // implementation plans associated with the plan.
    else {
      $form['practices']['#open'] = $this->plan->get('status')->value == 'planning' && empty($this->practicePlans);
    }

    // Do not open if the "open" query parameter is set, unless it is set to
    // "practices".
    // @todo https://github.com/farmier/farm_rcd/issues/57
    if ($this->getRequest()->query->has('open')) {
      $form['practices']['#open'] = FALSE;
      if ($this->getRequest()->query->get('open') == 'practices') {
        $form['practices']['#open'] = TRUE;
      }
    }

    // Build vertical tabs for each practice form.
    $form['practices']['tabs'] = [
      '#type' => 'vertical_tabs',
    ];

    // Provide simplified edit forms for practice implementation plans.
    foreach ($this->practicePlans as $id => $practicePlan) {

      // Details wrapper.
      $form['practices'][$id] = $this->buildPracticePlanForm($practicePlan);
      $form['practices'][$id]['#type'] = 'details';
      $form['practices'][$id]['#title'] = $practicePlan->label();
      $form['practices'][$id]['#description'] = $this->t('Practice implementation plan: <a href=":uri">%label</a>', [':uri' => $practicePlan->toUrl()->toString(), '%label' => $practicePlan->label()]);
      $form['practices'][$id]['#group'] = 'practices][tabs';
    }

    // Add a new practice implementation plan.
    $form['practices']['add'] = $this->buildPracticePlanForm();
    $form['practices']['add']['#type'] = 'details';
    $form['practices']['add']['#title'] = $this->t('+ Add practice');
    $form['practices']['add']['#description'] = $this->t('Create a new practice implementation plan.');

    // If there are practice implementation plans, show the add form in
    // vertical tabs. Otherwise, leave it ungrouped and open it by default.
    if (!empty($this->practicePlans)) {
      $form['practices']['add']['#group'] = 'practices][tabs';
    }
    else {
      $form['practices']['add']['#open'] = TRUE;
    }

    // Submit button.
    $form['practices']['actions'] = [
      '#type' => 'actions',
      '#weight' => 1000,
    ];
    $form['practices']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save conservation practices'),
    ];

    return $form;
  }

  /**
   * Practice implementation plan subform.
   *
   * @param \Drupal\plan\Entity\PlanInterface|null $plan
   *   The plan entity or NULL.
   *
   * @return array
   *   The render array defining the elements of the form.
   */
  protected function buildPracticePlanForm(?PlanInterface $plan = NULL) {

    // Plan ID (if available).
    $form['plan_id'] = [
      '#type' => 'value',
      '#value' => !is_null($plan) ? $plan->id() : NULL,
    ];

    // Location land asset reference.
    // If this is an existing practice implementation plan, show links to land
    // assets, but do not allow editing.
    if (!is_null($plan)) {
      /** @var \Drupal\asset\Entity\AssetInterface[] $land_assets */
      $land_assets = $plan->get('land')->referencedEntities();
      $form['location'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Land use area'),
        '#options' => array_combine(
          array_map(function ($asset) {
            return $asset->id();
          }, $land_assets),
          array_map(function ($asset) {
            return $asset->toLink()->toString();
          }, $land_assets),
        ),
        '#default_value' => array_map(function ($asset) {
          return $asset->id();
        }, $land_assets),
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

    // Build the name prefix of this sub-form for #states below.
    $states_name_prefix = !is_null($plan) ? 'practices[' . $plan->id() . ']' : 'practices[add]';

    // Practice.
    $form['practice'] = [
      '#type' => 'select',
      '#title' => $this->t('Practice'),
      '#options' => array_map(function ($practice) {
        $label = $practice['label']->render();
        if (!empty($practice['nrcs_code'])) {
          $label .= ' (NRCS code ' . $practice['nrcs_code'] . ')';
        }
        return $label;
      }, ConservationPractices::definitions()),
      '#default_value' => $plan ? $plan->get('rcd_practice')->value : NULL,
      '#states' => [
        'required' => [
          ':input[name="' . $states_name_prefix . '[location]"]' => ['filled' => TRUE],
        ],
      ],
      '#disabled' => !is_null($plan),
    ];

    // Other practice name.
    $form['practice_other'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Other practice name'),
      '#description' => $this->t('If "Other" was selected for the practice, give the practice a name.'),
      '#default_value' => $plan?->get('rcd_practice_other')->value,
      '#states' => [
        'visible' => [
          ':input[name="' . $states_name_prefix . '[practice]"]' => ['value' => 'other'],
        ],
      ],
    ];

    // The rest of the form is only shown when editing an existing plan.
    // This is primarily so that we can pre-populate the practice overview
    // without using Ajax. We put most other fields behind this step for
    // consistency.
    if (is_null($plan)) {

      // Add a description to the practice dropdown to explain this to the user.
      $form['practice']['#description'] = $this->t('Select a practice and click "Save conservation practices" below. Additional details about the practice can be entered after saving.');

      // Return the current state of the form.
      return $form;
    }

    // Practice geometry.
    $form['geometry'] = [
      '#type' => 'farm_map_input',
      '#title' => $this->t('Practice geometry'),
      '#description' => $this->t('Draw the practice implementation geometry using the map below, or paste geometry data (WKT, KML, or GeoJSON) into the box below the map. The property boundary is visible in blue and the current land use area is visible in orange.'),
      '#display_raw_geometry' => TRUE,
      '#default_value' => $plan->get('geometry')->value,
      '#map_type' => 'rcd',
      '#behaviors' => [
        'rcd_property_zoom',
      ],
      '#map_settings' => [
        'behaviors' => [
          'rcd_property_zoom' => [
            'property_geometry' => $this->property->get('geometry')->value,
          ],
        ],
      ],
    ];

    // Acreage/linear feet.
    // Conditionally show/hide based on the practice type. If "other" is
    // selected, then show both.
    $practice_name = 'practices[' . $plan->id() . '][practice]';
    $area_practices = array_filter(ConservationPractices::definitions(), function ($practice) {
      return $practice['unit'] == 'ac';
    });
    $linear_practices = array_filter(ConservationPractices::definitions(), function ($practice) {
      return $practice['unit'] == 'ft';
    });
    $form['acreage'] = [
      '#type' => 'number',
      '#title' => $this->t('Acreage'),
      '#min' => 0,
      '#step' => 0.1,
      '#default_value' => $plan->get('rcd_acres')->value,
      '#states' => [
        'visible' => [
          ':input[name="' . $practice_name . '"]' => array_merge(array_map(function ($key, $practice) {
            return ['value' => $key];
          }, array_keys($area_practices), $area_practices), [['value' => 'other']]),
        ],
      ],
    ];
    $form['linear_feet'] = [
      '#type' => 'number',
      '#title' => $this->t('Linear feet'),
      '#min' => 0,
      '#step' => 0.1,
      '#default_value' => $plan->get('rcd_linear_feet')->value,
      '#states' => [
        'visible' => [
          ':input[name="' . $practice_name . '"]' => array_merge(array_map(function ($key, $practice) {
            return ['value' => $key];
          }, array_keys($linear_practices), $linear_practices), [['value' => 'other']]),
        ],
      ],
    ];

    // Target implementation start date.
    $form['target_start_date'] = [
      '#type' => 'date',
      '#date_date_element' => 'date',
      '#date_time_element' => 'none',
      '#title' => $this->t('Target start date'),
      '#default_value' => !$plan->get('rcd_target_start_date')->isEmpty() ? date('Y-m-d', (int) $plan->get('rcd_target_start_date')->value) : NULL,
    ];

    // Target implementation end date.
    $form['target_end_date'] = [
      '#type' => 'date',
      '#date_date_element' => 'date',
      '#date_time_element' => 'none',
      '#title' => $this->t('Target end date'),
      '#default_value' => !$plan->get('rcd_target_end_date')->isEmpty() ? date('Y-m-d', (int) $plan->get('rcd_target_end_date')->value) : NULL,
    ];

    // Funding source.
    $form['funding_source'] = [
      '#type' => 'entity_autocomplete_tagify',
      '#title' => $this->t('Funding source'),
      '#target_type' => 'taxonomy_term',
      '#selection_handler' => 'default:taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => ['rcd_funding_source'],
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
      '#default_value' => $plan ? $plan->get('rcd_funding_source')->referencedEntities() : NULL,
    ];

    // Overview (plan notes).
    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Overview'),
      '#default_value' => $plan->get('notes')->value,
    ];

    // Funding source.
    $form['funding_source'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Funding source'),
      '#default_value' => $plan->get('rcd_funding_source')->value,
    ];

    // Status.
    /** @var \Drupal\state_machine\Plugin\Field\FieldType\StateItem $state_item */
    $state_item = $plan->get('status')->first();
    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => $state_item->getPossibleOptions(),
      '#default_value' => $plan->get('status')->value,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Filter submitted values to those with a numeric key (representing the
    // practice plan ID), or "add" (for adding a new practice plan).
    $practice_values = array_filter($form_state->getValue('practices'), function ($key) {
      return is_numeric($key) || $key === 'add';
    }, ARRAY_FILTER_USE_KEY);

    // If the "add" location field is empty, a new practice plan will not be
    // created.
    if (empty($practice_values['add']['location'])) {
      unset($practice_values['add']);
    }

    // For each set of values, generate/update and validate the practice plan.
    // The generatePracticePlan() method will return NULL if nothing has
    // changed on an existing plan, so we skip those.
    $plans = [];
    foreach ($practice_values as $values) {
      $plan = $this->generatePracticePlan($values);
      if (is_null($plan)) {
        continue;
      }
      $violations = $plan->validate();
      if ($violations->count() > 0) {
        $form_state->setErrorByName('', $this->t('The practice implementation plan did not pass validation.'));
        foreach ($violations as $violation) {
          $this->messenger()->addWarning($violation->getMessage());
        }
        return;
      }
      $plans[] = $plan;
    }

    // Save the plans to form state storage.
    $form_state->setStorage(['plans' => $plans]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Load the practice plans from storage.
    // Only new or modified plans will be included.
    $storage = $form_state->getStorage();
    $practice_plans = $storage['plans'] ?? [];

    // If there are no plans, bail.
    if (empty($practice_plans)) {
      $this->messenger()->addWarning($this->t('No practice implementation plans saved.'));
      return;
    }

    // Save the practice plans with revision log messages that reference the
    // plan. Keep track of logs that were created or updated so that we can
    // build a revision log message for the plan.
    $created_plans = [];
    $updated_plans = [];
    foreach ($practice_plans as $practice_plan) {
      if ($practice_plan->isNew()) {
        $practice_plan_revision = 'Created via <a href=":plan_uri">@plan_label</a>.';
        $created_plans[] = $practice_plan;
      }
      else {
        $practice_plan_revision = 'Updated via <a href=":plan_uri">@plan_label</a>.';
        $updated_plans[] = $practice_plan;
      }
      $plan_args = [
        ':plan_uri' => $this->plan->toUrl()->toString(),
        '@plan_label' => $this->plan->label(),
      ];
      $practice_plan->setRevisionLogMessage((string) new FormattableMarkup($practice_plan_revision, $plan_args));
      $practice_plan->save();
    }

    // Build a revision log message for the plan.
    $plan_revisions = [];
    if (!empty($created_plans)) {
      $plan_links = array_map(function (PlanInterface $practice_plan) {
        return (string) new FormattableMarkup('<a href=":uri">@label</a>', [':uri' => $practice_plan->toUrl()->toString(), '@label' => $practice_plan->label()]);
      }, $created_plans);
      $plan_revisions[] = 'Created practice implementation plan' . (count($plan_links) > 1 ? 's' : '') . ': ' . implode(', ', $plan_links) . '.';
    }
    if (!empty($updated_plans)) {
      $plan_links = array_map(function (PlanInterface $practice_plan) {
        return (string) new FormattableMarkup('<a href=":uri">@label</a>', [':uri' => $practice_plan->toUrl()->toString(), '@label' => $practice_plan->label()]);
      }, $updated_plans);
      $plan_revisions[] = 'Updated practice implementation plan' . (count($plan_links) > 1 ? 's' : '') . ': ' . implode(', ', $plan_links) . '.';
    }
    $plan_revision = implode(' ', $plan_revisions);

    // Save practice plans and revision log message to the resource
    // conservation plan. We need to add unchanging plans back to the list here
    // because we only have new/updated ones.
    $practice_plan_ids = array_map(function (PlanInterface $plan) {
      return $plan->id();
    }, $practice_plans);
    $unchanged_plans = array_filter($this->plan->get('practice_implementation_plan')->referencedEntities(), function (PlanInterface $plan) use ($practice_plan_ids) {
      return !in_array($plan->id(), $practice_plan_ids);
    });
    $this->plan->set('practice_implementation_plan', array_merge($practice_plans, $unchanged_plans));
    $this->plan->setRevisionLogMessage($plan_revision);
    $this->plan->save();

    // Show a message.
    $this->messenger()->addMessage($this->t('Practice implementation plans saved.'));

    // If new plans were created, set a query parameter to keep the form open.
    // @todo https://github.com/farmier/farm_rcd/issues/57
    if (!empty($created_plans)) {
      $form_state->setRedirectUrl($this->plan->toUrl()->setOption('query', ['open' => 'practices']));
    }
  }

  /**
   * Generate/update a practice plan entity from submitted values.
   *
   * @param array $values
   *   Submitted values from $form_state->getValue().
   *
   * @return \Drupal\plan\Entity\PlanInterface|null
   *   Returns an unsaved practice plan entity, or null if the plan already
   *   exists and nothing has changed on it.
   */
  protected function generatePracticePlan(array $values): ?PlanInterface {

    // If a plan ID is included, load it.
    // Otherwise, start a new one.
    $plan_storage = $this->entityTypeManager->getStorage('plan');
    if (!empty($values['plan_id'])) {
      /** @var \Drupal\plan\Entity\PlanInterface $plan */
      $plan = $plan_storage->load($values['plan_id']);
    }
    else {
      $asset_storage = $this->entityTypeManager->getStorage('asset');
      $land = $asset_storage->load($values['location']);
      /** @var \Drupal\plan\Entity\PlanInterface $plan */
      $plan = $plan_storage->create([
        'type' => 'rcd_practice_implementation',
        'farm' => [$this->farm],
        'land' => [$land],
        'owner' => $this->plan->get('owner'),
        'status' => 'planning',
      ]);
    }

    // Keep track of whether the plan is changed. New plans always are.
    $changed = $plan->isNew();

    // Set the name of the plan based on the land asset and practice.
    // Ensure the name is under 255 characters (we need to do this because the
    // user can't).
    $name = $plan->get('land')->referencedEntities()[0]->label();
    if ($values['practice'] == 'other') {
      $name .= ': ' . $values['practice_other'];
    }
    else {
      $practice_info = ConservationPractices::get($values['practice']);
      if (!is_null($practice_info)) {
        $name .= ': ' . $practice_info['label'];
      }
    }
    $name = mb_strimwidth($name, 0, 255, '…');
    if ($plan->get('name')->value != $name) {
      $plan->set('name', $name);
      $changed = TRUE;
    }

    // Fill in the plan details from form values, if available.
    $field_values = [
      'rcd_practice' => 'practice',
      'rcd_practice_other' => 'practice_other',
      'geometry' => 'geometry',
      'rcd_acres' => 'acreage',
      'rcd_linear_feet' => 'linear_feet',
      'rcd_target_start_date' => 'target_start_date',
      'rcd_target_end_date' => 'target_end_date',
      'notes' => 'notes',
      'rcd_funding_source' => 'funding_source',
      'status' => 'status',
    ];
    foreach ($field_values as $field => $name) {
      if (!isset($values[$name])) {
        continue;
      }
      $value = $values[$name];

      // If this is one of the date fields, convert value to timestamp.
      if (in_array($name, ['target_start_date', 'target_end_date'])) {
        $value = strtotime($value);
      }

      // Update the field on the plan.
      if ($plan->get($field)->value != $value) {
        $plan->set($field, $value);
        $changed = TRUE;
      }
    }

    // Process funding source taxonomy reference field.
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $name = 'funding_source';
    $vid = 'rcd_' . $name;
    $existing_ids = array_map(function ($term) {
      return $term->id();
    }, $plan->get($vid)->referencedEntities()
    );
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
      $plan->set($vid, []);
      $plan->set($vid, $updated_terms);
      $changed = TRUE;
    }

    // If the plan is new, populate the notes with a generic description of the
    // practice.
    if ($plan->isNew() && !empty($practice_info['description'])) {
      $plan->set('notes', $practice_info['description']);
    }

    // If the plan has changed, return it.
    // Otherwise, return NULL.
    if ($changed) {
      return $plan;
    }
    return NULL;
  }

}
