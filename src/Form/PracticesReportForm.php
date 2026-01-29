<?php

declare(strict_types=1);

namespace Drupal\farm_rcd\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\farm_rcd\ConservationPractices;
use Drupal\plan\Entity\PlanInterface;

/**
 * Practices report generator form.
 */
class PracticesReportForm extends FormBase {

  use AutowireTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PrivateTempStoreFactory $tempStoreFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'farm_rcd_practices_report_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?PlanInterface $plan = NULL) {
    $form = [];

    // Practice.
    $form['practice'] = [
      '#type' => 'select',
      '#title' => $this->t('Practice'),
      '#options' => array_merge([NULL => ''], array_map(function ($practice) {
        $label = $practice['label']->render();
        if (!empty($practice['nrcs_code'])) {
          $label .= ' (NRCS code ' . $practice['nrcs_code'] . ')';
        }
        return $label;
      }, ConservationPractices::definitions())),
    ];

    // Status.
    /** @var \Drupal\plan\Entity\PlanInterface $plan */
    $plan = $this->entityTypeManager->getStorage('plan')->create(['type' => 'rcd_practice_implementation']);
    /** @var \Drupal\state_machine\Plugin\Field\FieldType\StateItem $state_item */
    $state_item = $plan->get('status')->first();
    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => array_merge([NULL => ''], $state_item->getPossibleOptions()),
    ];

    // Submit button.
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate summary'),
    ];

    // Load saved results.
    $store = $this->tempStoreFactory->get('farm_rcd_practices_report');
    $key = (string) $this->currentUser()->id();
    $metadata = $store->getMetadata($key);
    $results = $store->get($key);

    // If there are saved results, display them.
    if (!empty($results)) {

      // Results wrapper.
      // Show when these results were generated.
      $form['results'] = [
        '#type' => 'details',
        '#title' => $this->t('Results'),
        '#description' => $this->t('Generated: %timestamp', ['%timestamp' => date('Y-m-d h:i:s a', $metadata->getUpdated())]),
        '#open' => TRUE,
      ];

      // Start a list of bulleted results.
      $items = [];

      // Practice types.
      if (!empty($results['practices'])) {
        $items[] = $this->t('Practices: %practices', ['%practices' => implode(', ', $results['practices'])]);
      }

      // Status of plans.
      if (!empty($results['status'])) {
        $items[] = $this->t('Status of implementations: %status', ['%status' => implode(', ', $results['status'])]);
      }

      // Total practices.
      if (!empty($results['plan_ids'])) {
        $items[] = $this->t('Total implementation plans: %count', ['%count' => count($results['plan_ids'])]);
      }

      // Total farms.
      if (!empty($results['farms'])) {
        $items[] = $this->t('Total farms: %count', ['%count' => count($results['farms'])]);
      }

      // Total acreage.
      if (!empty($results['acreage'])) {
        $items[] = $this->t('Total acreage: %total', ['%total' => $results['acreage']]);
      }

      // Total linear feet.
      if (!empty($results['linear_feet'])) {
        $items[] = $this->t('Total linear feet: %total', ['%total' => $results['linear_feet']]);
      }

      // Render the list of results.
      $form['results']['items'] = [
        '#theme' => 'item_list',
        '#items' => $items,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Run an entity query to find matching plans.
    // @phpstan-ignore method.alreadyNarrowedType
    $query = $this->entityTypeManager->getStorage('plan')->getQuery()->accessCheck(TRUE);
    $query->condition('type', 'rcd_practice_implementation');
    if (!empty($form_state->getValue('practice'))) {
      $query->condition('rcd_practice', $form_state->getValue('practice'));
    }
    if (!empty($form_state->getValue('status'))) {
      $query->condition('status', $form_state->getValue('status'));
    }
    $plan_ids = $query->execute();

    // Assemble batch operations.
    $operations = [];
    foreach ($plan_ids as $plan_id) {
      $operations[] = [
        [self::class, 'analyzePlan'],
        [$plan_id],
      ];
    }

    // Run the batch.
    $batch = [
      'title' => $this->t('Generating summary of practice implementations'),
      'operations' => $operations,
      'finished' => [self::class, 'finishBatch'],
    ];
    batch_set($batch);
  }

  /**
   * Batch operation callback for analyzing a plan.
   *
   * @param int $id
   *   The plan ID.
   * @param array $context
   *   The batch operation context, passed by reference.
   */
  public static function analyzePlan(int $id, array &$context): void {

    // Save the plan ID.
    $context['results']['plan_ids'][] = $id;

    // Load the plan.
    $plan = \Drupal::entityTypeManager()->getStorage('plan')->load($id);

    // Save the practice.
    if (!$plan->get('rcd_practice')->isEmpty()) {
      $practice = $plan->get('rcd_practice')->value;
      if (!isset($context['results']['practices'])) {
        $context['results']['practices'] = [];
      }

      // Get practice label to determine if it's already in the list.
      if ($practice == 'other' && !$plan->get('rcd_practice_other')->isEmpty()) {
        $practice_name = $plan->get('rcd_practice_other')->value;
      }
      else {
        $practice_name = ConservationPractices::get($practice)['label'];
      }

      if (!in_array($practice_name, $context['results']['practices'])) {
        $context['results']['practices'][] = $practice_name;
      }
    }

    // Save the plan status.
    if (!$plan->get('status')->isEmpty()) {
      /** @var \Drupal\state_machine\Plugin\Field\FieldType\StateItem $state_item */
      $state_item = $plan->get('status')->first();
      $status = $state_item->getLabel();
      if (!isset($context['results']['status'])) {
        $context['results']['status'] = [];
      }
      if (!in_array($status, $context['results']['status'])) {
        $context['results']['status'][] = $status;
      }
    }

    // Save the plan farm.
    if (!$plan->get('farm')->isEmpty()) {
      $farm_id = $plan->get('farm')->referencedEntities()[0]->id();
      if (!isset($context['results']['farms'])) {
        $context['results']['farms'] = [];
      }
      if (!in_array($farm_id, $context['results']['farms'])) {
        $context['results']['farms'][] = $farm_id;
      }
    }

    // Save the plan acreage.
    if (!$plan->get('rcd_acres')->isEmpty()) {
      $acres = $plan->get('rcd_acres')->value;
      if (!isset($context['results']['acreage'])) {
        $context['results']['acreage'] = $acres;
      }
      else {
        $context['results']['acreage'] += $acres;
      }
    }

    // Save the plan linear feet.
    if (!$plan->get('rcd_linear_feet')->isEmpty()) {
      $linear_ft = $plan->get('rcd_linear_feet')->value;
      if (!isset($context['results']['linear_feet'])) {
        $context['results']['linear_feet'] = $linear_ft;
      }
      else {
        $context['results']['linear_feet'] += $linear_ft;
      }
    }
  }

  /**
   * Batch finished callback for saving data to private temporary storage.
   *
   * @param bool $success
   *   Whether the batch was successful.
   * @param array $results
   *   The results from $context['results'].
   * @param array $operations
   *   Operations that remained unprocessed due to errors.
   */
  public static function finishBatch(bool $success, array $results, array $operations): void {

    // Load the Drupal messenger service.
    $messenger = \Drupal::messenger();

    // If an error occurred, notify the user.
    if (!$success) {
      $error_operation = reset($operations);
      $messenger->addMessage(
        t('An error occurred while processing @operation with arguments: @args',
          [
            '@operation' => $error_operation[0],
            '@args' => print_r($error_operation[0], TRUE),
          ]
        )
      );
    }

    // Save the results to private temporary storage.
    /** @var \Drupal\Core\TempStore\PrivateTempStore $store */
    $store = \Drupal::service('tempstore.private')->get('farm_rcd_practices_report');
    $store->set((string) \Drupal::currentUser()->id(), $results);

    // Show how many plans were analyzed.
    $plan_count = 0;
    if (!empty($results['plan_ids'])) {
      $plan_count = count($results['plan_ids']);
    }
    $messenger->addMessage(t('@count practice implementation plans analyzed.', ['@count' => $plan_count]));
  }

}
