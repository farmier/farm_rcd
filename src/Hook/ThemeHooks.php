<?php

declare(strict_types=1);

namespace Drupal\farm_rcd\Hook;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\farm_rcd\Form\IntakeReviewForm;
use Drupal\views\Views;

/**
 * Theme hook implementations for farm_rcd.
 */
class ThemeHooks implements ContainerInjectionInterface {

  use AutowireTrait;
  use StringTranslationTrait;

  public function __construct(
    protected AccountInterface $currentUser,
    protected FormBuilderInterface $formBuilder,
  ) {}

  /**
   * Implements hook_menu_local_actions_alter().
   */
  #[Hook('menu_local_actions_alter')]
  public function menuLocalActionsAlter(array &$local_actions): void {

    // Remove actions from dashboard.
    $actions = [
      'farm.actions:farm.add.asset',
      'farm.actions:farm.add.log',
      'farm.actions:farm.add.organization',
      'farm.actions:farm.add.plan',
    ];
    foreach ($actions as $action) {
      if (isset($local_actions[$action])) {
        $key = array_search('farm.dashboard', $local_actions[$action]['appears_on']);
        if ($key !== FALSE) {
          unset($local_actions[$action]['appears_on'][$key]);
        }
      }
    }
  }

  /**
   * Implements hook_preprocess_page().
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(&$variables): void {

    // Disable the breadcrumb region for anonymous users.
    if ($this->currentUser->isAnonymous()) {
      unset($variables['page']['breadcrumb']);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_view().
   */
  #[Hook('organization_view')]
  public function organizationView(array &$build, EntityInterface $organization, EntityViewDisplayInterface $display, $view_mode): void {

    // Only modify farm organizations in full view mode.
    if (!($organization->bundle() == 'farm' && $view_mode == 'full')) {
      return;
    }

    // Add the View of farm plans.
    $view = Views::getView('farm_rcd_farm_plan');
    $build['farm_plans'] = [
      '#type' => 'details',
      '#title' => $this->t('Plans'),
      '#open' => TRUE,
      '#access' => $view->access('default'),
      'view' => $view->buildRenderable('default', [$organization->id()]),
    ];
  }

  /**
   * Implements hook_ENTITY_TYPE_view().
   */
  #[Hook('plan_view')]
  public function planView(array &$build, EntityInterface $plan, EntityViewDisplayInterface $display, $view_mode): void {

    // Only modify RCP plans in full view mode.
    if (!($plan->bundle() == 'rcd_rcp' && $view_mode == 'full')) {
      return;
    }

    // Add planning workflow forms.
    $build['rcd_property'] = $this->formBuilder->getForm('Drupal\farm_rcd\Form\PropertyForm', $plan);
    $build['rcd_locations'] = $this->formBuilder->getForm('Drupal\farm_rcd\Form\LocationsForm', $plan);
    $build['rcd_site_assessments'] = $this->formBuilder->getForm('Drupal\farm_rcd\Form\SiteAssessmentsForm', $plan);
    $build['rcd_practices'] = $this->formBuilder->getForm('Drupal\farm_rcd\Form\PracticesForm', $plan);
    $build['rcd_document'] = $this->formBuilder->getForm('Drupal\farm_rcd\Form\DocumentForm', $plan);
    $build['rcd_email'] = $this->formBuilder->getForm('Drupal\farm_rcd\Form\DocumentEmailForm', $plan);
    $build['rcd_status'] = $this->formBuilder->getForm('Drupal\farm_rcd\Form\StatusForm', $plan);

    // Attach behavior for disabling forms when one is updated.
    $build['#attached']['drupalSettings']['disable_form_ids'] = [
      'farm-rcd-property-form',
      'farm-rcd-locations-form',
      'farm-rcd-site-assessment-form',
      'farm-rcd-practice-form',
      'farm-rcd-document-form',
      'farm-rcd-document-email-form',
      'farm-rcd-status-form',
      'comment-form',
    ];
    $build['#attached']['library'][] = 'farm_rcd/disable_forms';
  }

  /**
   * Implements hook_ENTITY_TYPE_view().
   */
  #[Hook('log_view')]
  public function logView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode): void {
    /** @var \Drupal\log\Entity\LogInterface $entity */

    // Only modify intake logs in full view mode.
    if (!($entity->bundle() == 'rcd_intake' && $view_mode == 'full')) {
      return;
    }

    // Add intake review form.
    $build['review_intake'] = [
      '#type' => 'details',
      '#title' => $this->t('Review intake'),
      '#open' => TRUE,
      '#access' => IntakeReviewForm::access($this->currentUser, $entity),
      'form' => $this->formBuilder->getForm('Drupal\farm_rcd\Form\IntakeReviewForm', $entity),
    ];

    // Add the View of plans associated with the intake.
    $view = Views::getView('farm_rcd_intake_plan');
    $build['intake_plans'] = [
      '#type' => 'details',
      '#title' => $this->t('Plans'),
      '#open' => TRUE,
      '#access' => $view->access('default'),
      'view' => $view->buildRenderable('default', [$entity->id()]),
    ];
  }

  /**
   * Implements hook_entity_extra_field_info().
   */
  #[Hook('entity_extra_field_info')]
  public function entityExtraFieldInfo(): array {

    // Expose the intake review form on intake logs.
    return [
      'log' => [
        'rcd_intake' => [
          'display' => [
            'review_intake' => [
              'label' => $this->t('Intake review form'),
              'description' => $this->t('Form for reviewing an intake log.'),
              'weight' => -100,
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Implements hook_farm_ui_theme_region_items().
   */
  #[Hook('farm_ui_theme_region_items')]
  public function farmUiThemeRegionItems(string $entity_type): array {

    // Place the View of farm plans in the bottom region.
    if ($entity_type == 'organization') {
      return [
        'bottom' => [
          'farm_plans',
        ],
      ];
    }

    // Place the planning workflow forms in the bottom region.
    if ($entity_type == 'plan') {
      return [
        'bottom' => [
          'rcd_property',
          'rcd_locations',
          'rcd_site_assessments',
          'rcd_practices',
          'rcd_document',
          'rcd_email',
          'rcd_status',
        ],
      ];
    }

    // Place the intake review form in the top region.
    if ($entity_type == 'log') {
      return [
        'top' => [
          'review_intake',
        ],
        'bottom' => [
          'intake_plans',
        ],
      ];
    }

    return [];
  }

}
