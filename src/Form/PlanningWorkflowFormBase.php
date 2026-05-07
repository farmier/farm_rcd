<?php

declare(strict_types=1);

namespace Drupal\farm_rcd\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\asset\Entity\AssetInterface;
use Drupal\farm_form\Traits\FarmFormProtectionTrait;
use Drupal\log\Entity\LogInterface;
use Drupal\organization\Entity\OrganizationInterface;
use Drupal\plan\Entity\PlanInterface;

/**
 * Base form class for planning workflow forms.
 */
abstract class PlanningWorkflowFormBase extends FormBase {

  use AutowireTrait;
  use FarmFormProtectionTrait;

  /**
   * Plan entity.
   *
   * @var \Drupal\plan\Entity\PlanInterface|null
   */
  protected ?PlanInterface $plan = NULL;

  /**
   * Intake log.
   *
   * @var \Drupal\log\Entity\LogInterface|null
   */
  protected ?LogInterface $intake = NULL;

  /**
   * Farm organization.
   *
   * @var \Drupal\organization\Entity\OrganizationInterface|null
   */
  protected ?OrganizationInterface $farm = NULL;

  /**
   * Property land asset.
   *
   * @var \Drupal\asset\Entity\AssetInterface|null
   */
  protected ?AssetInterface $property = NULL;

  /**
   * Land assets that are children of the property.
   *
   * @var \Drupal\asset\Entity\AssetInterface[]
   */
  protected array $landAssets = [];

  /**
   * Site assessment logs associated with the land assets.
   *
   * @var \Drupal\log\Entity\LogInterface[]
   */
  protected array $siteAssessmentLogs = [];

  /**
   * Practice implementation plans associated with the RCP.
   *
   * @var \Drupal\plan\Entity\PlanInterface[]
   */
  protected array $practicePlans = [];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?PlanInterface $plan = NULL) {
    $form = ['#tree' => TRUE];
    $this->loadPlanEntities($plan);
    $this->enableFormProtection($form);
    return $form;
  }

  /**
   * Load entities relevant to the plan.
   *
   * @param \Drupal\plan\Entity\PlanInterface|null $plan
   *   The plan entity, or null.
   */
  protected function loadPlanEntities(?PlanInterface $plan = NULL) {

    // Save the plan, or bail if it is null.
    if (is_null($plan)) {
      return;
    }
    $this->plan = $plan;

    // If the plan has an intake associated with it, load the log.
    if (!$plan->get('intake')->isEmpty()) {
      $this->intake = $plan->get('intake')->referencedEntities()[0];
    }

    // If the plan has a farm associated with it, load the organization.
    if (!$plan->get('farm')->isEmpty()) {
      $this->farm = $plan->get('farm')->referencedEntities()[0];
    }

    // If the plan has a property associated with it, load the asset.
    if (!$plan->get('property')->isEmpty()) {
      $this->property = $plan->get('property')->referencedEntities()[0];
    }

    // If a property exists, load any land assets that are children of it.
    if (!is_null($this->property)) {
      $this->landAssets = $this->entityTypeManager->getStorage('asset')->loadByProperties([
        'type' => 'land',
        'parent' => $this->property->id(),
        'archived' => FALSE,
      ]);
    }

    // If there are land assets, load any site assessment logs associated with
    // them.
    if (!empty($this->landAssets)) {
      $land_asset_ids = array_map(function ($asset) {
        return $asset->id();
      }, $this->landAssets);
      $log_storage = $this->entityTypeManager->getStorage('log');
      // @see https://github.com/mglaman/phpstan-drupal/issues/825
      // @phpstan-ignore method.alreadyNarrowedType
      $site_assessment_ids = $log_storage
        ->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'rcd_site_assessment')
        ->condition('location', $land_asset_ids, 'IN')
        ->execute();
      $this->siteAssessmentLogs = $log_storage->loadMultiple($site_assessment_ids);
    }

    // If there are practice implementation plans associated with the plan,
    // load them. Ensure that they are indexed by plan ID, because
    // EntityReferenceFieldItemListInterface::referencedEntities() does not.
    if (!$plan->get('practice_implementation_plan')->isEmpty()) {
      foreach ($plan->get('practice_implementation_plan')->referencedEntities() as $plan) {
        $this->practicePlans[$plan->id()] = $plan;
      }
    }
  }

  /**
   * Given a term name, create or load a matching term entity.
   *
   * @param string $name
   *   The term name.
   * @param string $vocabulary
   *   The vocabulary to search or create in.
   *
   * @return \Drupal\taxonomy\TermInterface
   *   The term entity that was created or loaded.
   */
  public function createOrLoadTerm(string $name, string $vocabulary) {

    // Get term entity storage.
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

    // First try to load an existing term.
    /** @var \Drupal\taxonomy\TermInterface[] $search */
    $search = $term_storage->loadByProperties(['name' => $name, 'vid' => $vocabulary]);
    if (!empty($search)) {
      $term = reset($search);
    }

    // Otherwise, create a new term.
    else {
      $term = $term_storage->create([
        'name' => $name,
        'vid' => $vocabulary,
      ]);
      $term->save();
    }

    return $term;
  }

}
