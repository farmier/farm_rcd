<?php

declare(strict_types=1);

namespace Drupal\farm_rcd\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\farm_rcd\RcdOptionLists;
use Drupal\log\Entity\Log;
use Drupal\log\Entity\LogInterface;

/**
 * Intake form.
 */
class IntakeForm extends FormBase {

  use AutowireTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountInterface $currentUser,
    protected MailManagerInterface $mailManager,
    protected FloodInterface $flood,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'farm_rcd_intake_form';
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {

    // Allow anonymous access.
    return AccessResult::allowed();
  }

  /**
   * Define the steps in this multistep form.
   */
  protected function steps(): array {
    return [
      'intro' => [
        'label' => $this->t('Introduction'),
        'message' => '',
        'callback' => 'buildIntroForm',
        'progress' => 0,
      ],
      'stakeholder' => [
        'label' => $this->t('Stakeholder information'),
        'message' => $this->t('Step @num of @total', ['@num' => 1, '@total' => 3]),
        'callback' => 'buildStakeholderForm',
        'progress' => 25,
      ],
      'property' => [
        'label' => $this->t('Property description'),
        'message' => $this->t('Step @num of @total', ['@num' => 2, '@total' => 3]),
        'callback' => 'buildPropertyForm',
        'progress' => 50,
      ],
      'interests' => [
        'label' => $this->t('Stakeholder interests'),
        'message' => $this->t('Step @num of @total', ['@num' => 3, '@total' => 3]),
        'callback' => 'buildInterestsForm',
        'progress' => 75,
      ],
      'review' => [
        'label' => $this->t('Review'),
        'message' => '',
        'callback' => 'buildReviewForm',
        'progress' => 100,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;

    // If flood control restrictions have been exceeded, display a message.
    // A threshold of 2 means that the form can be submitted once per hour.
    if (!$this->flood->isAllowed('rcd_intake_form', 2)) {
      $form['status'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('You have already submitted the form. Please try again later or contact your RCD directly.'),
      ];
      return $form;
    }

    // If the form has been submitted, only display a message to the user.
    if ($form_state->has('submitted') && $form_state->get('submitted')) {
      $form['#markup'] = $this->t('Thank you for your interest. A staff member will review your information and follow up with you shortly.');
      return $form;
    }

    // This is a multistep form. We track which step we are on via a step
    // property in $form_state. Each step has a corresponding form method that
    // we use to build it.
    $step = 'intro';
    if ($form_state->has('step') && array_key_exists($form_state->get('step'), $this->steps())) {
      $step = $form_state->get('step');
    }

    // Show a progress bar if progress is greater than 0.
    if ($this->steps()[$step]['progress'] > 0) {
      $form['progress'] = [
        '#theme' => 'progress_bar',
        '#label' => $this->steps()[$step]['label'],
        '#percent' => $this->steps()[$step]['progress'],
        '#message' => $this->steps()[$step]['message'],
      ];

      // Fix issue with progress library not being added in some contexts (eg:
      // the intake form for anonymous users).
      // @see https://www.drupal.org/project/drupal/issues/3540259
      $form['progress']['#attached']['library'][] = 'core/drupal.progress';
    }

    // Load saved values for this step.
    // The review step gets all saved values.
    $saved_values = [];
    if ($form_state->has('saved_values')) {
      $saved_values = $form_state->get('saved_values');
      if ($step != 'review' && isset($saved_values[$step])) {
        $saved_values = $saved_values[$step];
      }
    }

    // Load the appropriate form.
    $form[$step] = $this->{$this->steps()[$step]['callback']}($saved_values);

    // Create form actions.
    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => 1000,
    ];

    // Add "Next" and "Back" buttons depending on the step we're on.
    if ($this->steps()[$step]['progress'] > 0) {
      $form['actions']['back'] = [
        '#type' => 'submit',
        '#value' => $this->t('Back'),
        '#submit' => [[$this, 'submitBack']],
      ];
    }
    if ($step != array_key_last($this->steps())) {
      $form['actions']['next'] = [
        '#type' => 'submit',
        '#value' => $this->t('Next'),
        '#submit' => [[$this, 'submitNext']],
      ];
    }

    // Add the submit button, but only make it accessible on the last step.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#validate' => [[$this, 'validateIntake']],
      '#access' => $step == array_key_last($this->steps()),
    ];

    return $form;
  }

  /**
   * Build the intro page of the intake form.
   *
   * @param array $saved_values
   *   Saved values for this step.
   *
   * @return array
   *   The render array defining the elements of the form.
   */
  public function buildIntroForm(array $saved_values) {

    // Introductory text.
    $form['text1'] = [
      '#type' => 'item',
      '#markup' => $this->t('Please complete this form to express interest in working with your local Resource Conservation District.'),
    ];
    $form['text2'] = [
      '#type' => 'item',
      '#markup' => $this->t('An RCD staff member will contact you to discuss the practices that best align to your goals for your land. Conservation practices identified may help with water management / retention, soil quality, erosion reduction, cost savings, and reduced climate impacts.'),
    ];
    $form['text3'] = [
      '#type' => 'item',
      '#markup' => $this->t('If you decide to pursue any of the practices identified, then RCD staff will help to secure funding and provide technical assistance for implementation.'),
    ];

    return $form;
  }

  /**
   * Build the stakeholder page of the intake form.
   *
   * @param array $saved_values
   *   Saved values for this step.
   *
   * @return array
   *   The render array defining the elements of the form.
   */
  public function buildStakeholderForm(array $saved_values) {

    // Personal information section.
    $form['personal'] = [
      '#type' => 'details',
      '#title' => $this->t('Personal information'),
      '#open' => TRUE,
    ];

    // Stakeholder name.
    $form['personal']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Stakeholder name'),
      '#description' => $this->t('If Stakeholder is a Company, state the name of the Company. If the Stakeholder is the owner of a registered business name, state the business name and the name(s) of the owner(s). If Stakeholder is a person applying in his/her own name, state the name of the Stakeholder.'),
      '#default_value' => $saved_values['personal']['name'] ?? '',
      '#required' => TRUE,
    ];

    // Stakeholder type.
    $form['personal']['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Stakeholder type'),
      '#options' => RcdOptionLists::stakeholderTypes(),
      '#default_value' => $saved_values['personal']['type'] ?? NULL,
      '#required' => TRUE,
    ];

    // Stakeholder email.
    $form['personal']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#default_value' => $saved_values['personal']['email'] ?? '',
      '#required' => TRUE,
    ];

    // Send stakeholder email toggle. Don't display to anonymous users.
    $form['personal']['send_email'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send email to stakeholder'),
      '#description' => $this->t('Uncheck this if you do not want to notify the stakeholder that this intake was created.'),
      '#default_value' => TRUE,
      '#access' => $this->currentUser()->id() > 0,
    ];

    // Stakeholder phone.
    $form['personal']['phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone'),
      '#default_value' => $saved_values['personal']['phone'] ?? '',
    ];

    // Stakeholder mailing address section.
    $form['personal']['address'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Stakeholder mailing address'),
    ];

    // Stakeholder mailing address: street.
    $form['personal']['address']['street'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Street'),
      '#default_value' => $saved_values['personal']['address']['street'] ?? '',
      '#required' => TRUE,
    ];

    // Stakeholder mailing address: city.
    $form['personal']['address']['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#default_value' => $saved_values['personal']['address']['city'] ?? '',
      '#required' => TRUE,
    ];

    // Stakeholder mailing address: state.
    $form['personal']['address']['state'] = [
      '#type' => 'select',
      '#title' => $this->t('State'),
      '#options' => RcdOptionLists::states(),
      '#default_value' => $saved_values['personal']['address']['state'] ?? NULL,
      '#required' => TRUE,
    ];

    // Stakeholder mailing address: postal code.
    $form['personal']['address']['zip'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postal code'),
      '#default_value' => $saved_values['personal']['address']['zip'] ?? '',
      '#required' => TRUE,
    ];

    // Stakeholder group.
    $form['personal']['group'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Many grants are prioritized for specific groups of farmers and ranchers. Please let us know if you or a property owner identify as any of the following as it could increase likelihood of funding projects on your land (choose all that apply):'),
      '#description' => $this->t('To read more about these categories, <a href=":url" target="_blank">click here</a>.', [':url' => 'https://www.cdfa.ca.gov/farmequity/']),
      '#options' => RcdOptionLists::stakeholderGroups(),
      '#default_value' => $saved_values['personal']['group'] ?? [],
    ];

    // Stakeholder group other field.
    $form['personal']['group_other'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Specify other group'),
      '#default_value' => $saved_values['personal']['group_other'] ?? '',
      '#states' => [
        'required' => [
          ':input[name="stakeholder[personal][group][other]"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[name="stakeholder[personal][group][other]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Share with other RCDs.
    $form['personal']['share_rcds'] = [
      '#type' => 'radios',
      '#title' => $this->t('Would you like to share the application information with other RCDs?'),
      '#description' => $this->t('You have the right to submit the application and not to share the information with other RCDs. However, allowing your application information to be shared will allow the RCDs in the State to follow more transparently the development of your conservation practices in order to collaborate and share best practices.'),
      '#options' => [
        'yes' => $this->t('Yes'),
        'no' => $this->t('No'),
      ],
      '#default_value' => $saved_values['personal']['share_rcds'] ?? NULL,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * Build the property page of the intake form.
   *
   * @param array $saved_values
   *   Saved values for this step.
   *
   * @return array
   *   The render array defining the elements of the form.
   */
  public function buildPropertyForm(array $saved_values) {

    // Property information section.
    $form['info'] = [
      '#type' => 'details',
      '#title' => $this->t('Property information'),
      '#open' => TRUE,
    ];

    // Property name.
    $form['info']['farm_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property name'),
      '#default_value' => $saved_values['info']['farm_name'] ?? '',
    ];

    // Own or lease the land?
    $form['info']['own_or_lease'] = [
      '#type' => 'radios',
      '#title' => $this->t('Is the land owned or leased?'),
      '#options' => [
        'own' => $this->t('Own'),
        'lease' => $this->t('Lease'),
        'unknown' => $this->t('Unknown'),
      ],
      '#default_value' => $saved_values['info']['own_or_lease'] ?? '',
      '#required' => TRUE,
    ];

    // Property owner.
    $form['info']['owner'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Who is the property owner?'),
      '#default_value' => $saved_values['info']['owner'] ?? '',
      '#required' => TRUE,
    ];

    // Lease expiration.
    $form['info']['lease_expiration'] = [
      '#type' => 'date',
      '#title' => $this->t('When does the lease expire?'),
      '#default_value' => $saved_values['info']['lease_expiration'] ?? NULL,
      '#states' => [
        'visible' => [
          ':input[name="property[info][own_or_lease]"]' => ['value' => 'lease'],
        ],
      ],
    ];

    // Approximate total acreage.
    $form['info']['acreage'] = [
      '#type' => 'number',
      '#title' => $this->t('Approximate total acreage'),
      '#description' => $this->t('If exact acreage is not known please provide the approximate acreage of the land, so we can get a sense of your project.'),
      '#min' => 0,
      '#step' => 0.1,
      '#default_value' => $saved_values['info']['acreage'] ?? '',
      '#required' => TRUE,
    ];

    // Property has address?
    $form['info']['has_address'] = [
      '#type' => 'radios',
      '#title' => $this->t('Does the land have an address?'),
      '#options' => [
        'yes' => $this->t('Yes'),
        'no' => $this->t('No'),
      ],
      '#default_value' => $saved_values['info']['has_address'] ?? NULL,
      '#required' => TRUE,
    ];

    // Property address: street.
    $form['info']['street'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Street'),
      '#default_value' => $saved_values['info']['street'] ?? '',
      '#states' => [
        'required' => [
          ':input[name="property[info][has_address]"]' => ['value' => 'yes'],
        ],
        'visible' => [
          ':input[name="property[info][has_address]"]' => ['value' => 'yes'],
        ],
      ],
    ];

    // Property address: city.
    $form['info']['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#default_value' => $saved_values['info']['city'] ?? '',
      '#states' => [
        'required' => [
          ':input[name="property[info][has_address]"]' => ['value' => 'yes'],
        ],
        'visible' => [
          ':input[name="property[info][has_address]"]' => ['value' => 'yes'],
        ],
      ],
    ];

    // Property address: state.
    $form['info']['state'] = [
      '#type' => 'select',
      '#title' => $this->t('State'),
      '#options' => RcdOptionLists::states(),
      '#default_value' => $saved_values['info']['state'] ?? NULL,
      '#states' => [
        'required' => [
          ':input[name="property[info][has_address]"]' => ['value' => 'yes'],
        ],
        'visible' => [
          ':input[name="property[info][has_address]"]' => ['value' => 'yes'],
        ],
      ],
    ];

    // Property address: postal code.
    $form['info']['zip'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postal code'),
      '#default_value' => $saved_values['info']['zip'] ?? '',
      '#states' => [
        'required' => [
          ':input[name="property[info][has_address]"]' => ['value' => 'yes'],
        ],
        'visible' => [
          ':input[name="property[info][has_address]"]' => ['value' => 'yes'],
        ],
      ],
    ];

    // Property address: parcel number or GPS coordinates.
    $form['info']['parcel_gps'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Parcel number or GPS coordinates'),
      '#description' => $this->t('Please provide a parcel number or GPS coordinates to help locate the property.'),
      '#default_value' => $saved_values['info']['parcel_gps'] ?? '',
      '#states' => [
        'required' => [
          ':input[name="property[info][has_address]"]' => ['value' => 'no'],
        ],
      ],
    ];

    // Land use section.
    $form['land_use'] = [
      '#type' => 'details',
      '#title' => $this->t('Current land use and acreage'),
      '#open' => TRUE,
    ];

    // Land use checkboxes.
    $form['land_use']['land_use'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select at least one'),
      '#options' => RcdOptionLists::landUses(),
      '#default_value' => $saved_values['land_use']['land_use'] ?? [],
      '#required' => TRUE,
    ];

    // Grazing acreage.
    $form['land_use']['grazing_acreage'] = [
      '#type' => 'number',
      '#title' => $this->t('Grazing acreage'),
      '#min' => 0,
      '#step' => 0.1,
      '#default_value' => $saved_values['land_use']['grazing_acreage'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="property[land_use][land_use][grazing]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Vineyards acreage.
    $form['land_use']['vineyards_acreage'] = [
      '#type' => 'number',
      '#title' => $this->t('Vineyards acreage'),
      '#min' => 0,
      '#step' => 0.1,
      '#default_value' => $saved_values['land_use']['vineyards_acreage'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="property[land_use][land_use][vineyards]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Orchards acreage.
    $form['land_use']['orchards_acreage'] = [
      '#type' => 'number',
      '#title' => $this->t('Orchards acreage'),
      '#min' => 0,
      '#step' => 0.1,
      '#default_value' => $saved_values['land_use']['orchards_acreage'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="property[land_use][land_use][orchards]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Row crops acreage.
    $form['land_use']['rowcrops_acreage'] = [
      '#type' => 'number',
      '#title' => $this->t('Row crops acreage'),
      '#min' => 0,
      '#step' => 0.1,
      '#default_value' => $saved_values['land_use']['rowcrops_acreage'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="property[land_use][land_use][rowcrops]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Natural lands acreage.
    $form['land_use']['natural_acreage'] = [
      '#type' => 'number',
      '#title' => $this->t('Natural lands acreage'),
      '#min' => 0,
      '#step' => 0.1,
      '#default_value' => $saved_values['land_use']['natural_acreage'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="property[land_use][land_use][natural]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Other land use.
    $form['land_use']['other'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Specify other land usage'),
      '#default_value' => $saved_values['land_use']['other'] ?? '',
      '#states' => [
        'required' => [
          ':input[name="property[land_use][land_use][other]"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[name="property[land_use][land_use][other]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Other land use acreage.
    $form['land_use']['other_acreage'] = [
      '#type' => 'number',
      '#title' => $this->t('Other land use acreage'),
      '#min' => 0,
      '#step' => 0.1,
      '#default_value' => $saved_values['land_use']['other_acreage'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="property[land_use][land_use][other]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Crop types (relevant for vineyards, orchards, and row crops).
    $form['land_use']['crop_types'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Crop types'),
      '#default_value' => $saved_values['land_use']['crop_types'] ?? '',
      '#states' => [
        'visible' => [
          [':input[name="property[land_use][land_use][orchards]"]' => ['checked' => TRUE]],
          'or',
          [':input[name="property[land_use][land_use][rowcrops]"]' => ['checked' => TRUE]],
        ],
      ],
    ];

    return $form;
  }

  /**
   * Build the interests page of the intake form.
   *
   * @param array $saved_values
   *   Saved values for this step.
   *
   * @return array
   *   The render array defining the elements of the form.
   */
  public function buildInterestsForm(array $saved_values) {

    // Goals wrapper.
    $form['goals'] = [
      '#type' => 'details',
      '#title' => $this->t('Goals'),
      '#description' => $this->t('What are your goals for working with our Resource Conservation District?'),
      '#open' => TRUE,
    ];

    // Stakeholder goals checklist.
    $form['goals']['goals'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Please select at least one'),
      '#options' => RcdOptionLists::goals(),
      '#default_value' => $saved_values['goals']['goals'] ?? [],
      '#required' => TRUE,
    ];

    // Other goals.
    $form['goals']['other'] = [
      '#type' => 'textfield',
      '#title' => $this->t('If other, please elaborate'),
      '#default_value' => $saved_values['goals']['other'] ?? '',
      '#states' => [
        'required' => [
          ':input[name="interests[goals][goals][other]"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[name="interests[goals][goals][other]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Interests wrapper.
    $form['concerns'] = [
      '#type' => 'details',
      '#title' => $this->t('Resource Concerns'),
      '#description' => $this->t('Do you need help addressing natural resource concerns related to any of the following areas on your property?'),
      '#open' => TRUE,
    ];

    // Stakeholder concerns checklist.
    $form['concerns']['concerns'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Please select at least one'),
      '#options' => RcdOptionLists::concerns(),
      '#default_value' => $saved_values['concerns']['concerns'] ?? [],
      '#required' => TRUE,
    ];

    // Other concerns.
    $form['concerns']['other'] = [
      '#type' => 'textfield',
      '#title' => $this->t('If other, please elaborate'),
      '#default_value' => $saved_values['concerns']['other'] ?? '',
      '#states' => [
        'required' => [
          ':input[name="interests[concerns][concerns][other]"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[name="interests[concerns][concerns][other]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Additional comments.
    $form['comments'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Additional comments'),
      '#default_value' => $saved_values['comments'] ?? '',
    ];

    return $form;
  }

  /**
   * Build the review page of the intake form.
   *
   * @param array $saved_values
   *   Saved values for this step.
   *
   * @return array
   *   The render array defining the elements of the form.
   */
  public function buildReviewForm(array $saved_values) {
    $form = [];

    // Generate log entity.
    $log = $this->generateIntakeLog($saved_values);

    // Render the log entity.
    $form['log'] = $this->entityTypeManager->getViewBuilder('log')->view($log, 'rcd_intake_preview');

    return $form;
  }

  /**
   * Submit handler for the "Back" button.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitBack(array &$form, FormStateInterface $form_state) {

    // Save current values to form state.
    $values = $form_state->getValues();
    if ($form_state->has('saved_values')) {
      $values = array_merge($form_state->get('saved_values'), $values);
    }
    $form_state->set('saved_values', $values);

    // Go back one step.
    $step = $form_state->get('step');
    $steps = array_keys($this->steps());
    $position = array_search($step, $steps);
    $previous_step = ($position > 0) ? $steps[$position - 1] : NULL;
    $form_state->set('step', $previous_step);

    // Rebuild the form.
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler for the "Next" button.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitNext(array &$form, FormStateInterface $form_state) {

    // Save current values to form state.
    $values = $form_state->getValues();
    if ($form_state->has('saved_values')) {
      $values = array_merge($form_state->get('saved_values'), $values);
    }
    $form_state->set('saved_values', $values);

    // Go forward one step.
    $step = $form_state->get('step');
    $steps = array_keys($this->steps());
    $position = array_search($step, $steps);
    $next_step = ($position < count($steps) - 1) ? $steps[$position + 1] : NULL;
    $form_state->set('step', $next_step);

    // Rebuild the form.
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function validateIntake(array &$form, FormStateInterface $form_state) {

    // Load and validate saved values.
    $saved_values = $form_state->get('saved_values');
    if (is_null($saved_values)) {
      $form_state->setErrorByName('', $this->t('An error occurred. Please contact the system administrator.'));
      return;
    }

    // Generate and validate rcd_intake log.
    $log = $this->generateIntakeLog($saved_values);
    $violations = $log->validate();
    if ($violations->count() > 0) {
      $form_state->setErrorByName('', $this->t('A validation error occurred. Please contact the system administrator.'));
      foreach ($violations as $violation) {
        $this->messenger()->addWarning($violation->getMessage());
      }
      return;
    }

    // Save the generated saved values and log to form state storage. Saving
    // saved_values allows us to access properties like send_email later that
    // won't be available on the log.
    $form_state->setStorage(['saved_values' => $saved_values, 'log' => $log]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Register a flood control event if this is an anonymous user.
    if ($this->currentUser->isAnonymous()) {
      $this->flood->register('rcd_intake_form');
    }

    // Load the log from storage and save it.
    $storage = $form_state->getStorage();
    $storage['log']->save();

    // Email the stakeholder and RCD staff.
    $send_stakeholder_email = $storage['saved_values']['stakeholder']['personal']['send_email'];
    if (!$storage['log']->get('intake_stakeholder_email')->isEmpty() && $send_stakeholder_email) {
      $this->mailManager->mail('farm_rcd', 'intake_received_stakeholder', $storage['log']->get('intake_stakeholder_email')->value, 'en');
    }
    if (!empty($this->configFactory()->get('farm_rcd.settings')->get('intake_email'))) {
      $params = [
        'log' => $storage['log'],
      ];
      $this->mailManager->mail('farm_rcd', 'intake_received_staff', $this->configFactory()->get('farm_rcd.settings')->get('intake_email'), 'en', $params);
    }

    // Remember that the form was submitted, so we can display a message to
    // the user.
    $form_state->set('submitted', TRUE);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Generate a rcd_intake log entity from saved values.
   *
   * @param array $saved_values
   *   Saved values for this step.
   *
   * @return \Drupal\log\Entity\LogInterface|null
   *   Returns an unsaved rcd_intake log entity, or null if something goes
   *   wrong.
   */
  protected function generateIntakeLog(array $saved_values): ?LogInterface {

    // Convert date to timestamp.
    $intake_property_lease_exp = $saved_values['property']['info']['lease_expiration'] ? strtotime($saved_values['property']['info']['lease_expiration']) : NULL;

    // Process checkboxes.
    $intake_stakeholder_group = isset($saved_values['stakeholder']['personal']['group']) ? array_keys(array_filter($saved_values['stakeholder']['personal']['group'])) : NULL;
    $intake_property_use = isset($saved_values['property']['land_use']['land_use']) ? array_keys(array_filter($saved_values['property']['land_use']['land_use'])) : NULL;
    $intake_goals = isset($saved_values['interests']['goals']['goals']) ? array_keys(array_filter($saved_values['interests']['goals']['goals'])) : NULL;
    $intake_concerns = isset($saved_values['interests']['concerns']['concerns']) ? array_keys(array_filter($saved_values['interests']['concerns']['concerns'])) : NULL;

    // Process booleans.
    $intake_rcd_sharing_allowed = $saved_values['stakeholder']['personal']['share_rcds'] === 'yes';

    // Create and return the log.
    return Log::create([
      'type' => 'rcd_intake',
      'intake_stakeholder_name' => $saved_values['stakeholder']['personal']['name'],
      'intake_stakeholder_type' => $saved_values['stakeholder']['personal']['type'],
      'intake_stakeholder_email' => $saved_values['stakeholder']['personal']['email'],
      'intake_stakeholder_phone' => $saved_values['stakeholder']['personal']['phone'],
      'intake_stakeholder_street' => $saved_values['stakeholder']['personal']['address']['street'],
      'intake_stakeholder_city' => $saved_values['stakeholder']['personal']['address']['city'],
      'intake_stakeholder_state' => $saved_values['stakeholder']['personal']['address']['state'],
      'intake_stakeholder_zip' => $saved_values['stakeholder']['personal']['address']['zip'],
      'intake_stakeholder_group' => $intake_stakeholder_group,
      'intake_stakeholder_group_other' => $saved_values['stakeholder']['personal']['group_other'],
      'intake_farm_name' => $saved_values['property']['info']['farm_name'],
      'intake_property_own_or_lease' => $saved_values['property']['info']['own_or_lease'],
      'intake_property_owner' => $saved_values['property']['info']['owner'],
      'intake_property_lease_exp' => $intake_property_lease_exp,
      'intake_property_acreage' => $saved_values['property']['info']['acreage'],
      'intake_property_street' => $saved_values['property']['info']['street'],
      'intake_property_city' => $saved_values['property']['info']['city'],
      'intake_property_state' => $saved_values['property']['info']['state'],
      'intake_property_zip' => $saved_values['property']['info']['zip'],
      'intake_property_parcel_gps' => $saved_values['property']['info']['parcel_gps'],
      'intake_property_use' => $intake_property_use,
      'intake_property_use_grazing_ac' => $saved_values['property']['land_use']['grazing_acreage'],
      'intake_property_use_vineyard_ac' => $saved_values['property']['land_use']['vineyards_acreage'],
      'intake_property_use_orchard_ac' => $saved_values['property']['land_use']['orchards_acreage'],
      'intake_property_use_rowcrop_ac' => $saved_values['property']['land_use']['rowcrops_acreage'],
      'intake_property_use_natural_ac' => $saved_values['property']['land_use']['natural_acreage'],
      'intake_property_use_other' => $saved_values['property']['land_use']['other'],
      'intake_property_use_other_ac' => $saved_values['property']['land_use']['other_acreage'],
      'intake_property_use_crop_types' => $saved_values['property']['land_use']['crop_types'],
      'intake_goals' => $intake_goals,
      'intake_goals_other' => $saved_values['interests']['goals']['other'],
      'intake_concerns' => $intake_concerns,
      'intake_concerns_other' => $saved_values['interests']['concerns']['other'],
      'intake_comments' => $saved_values['interests']['comments'],
      'intake_rcd_sharing_allowed' => $intake_rcd_sharing_allowed,
      'status' => 'pending',
    ]);
  }

}
