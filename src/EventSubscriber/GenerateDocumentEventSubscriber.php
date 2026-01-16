<?php

declare(strict_types=1);

namespace Drupal\farm_rcd\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\farm_rcd\ConservationPractices;
use Drupal\farm_rcd\Event\GenerateDocumentEvent;
use Drupal\farm_rcd\Placeholder\ListBlockPlaceholder;
use Drupal\farm_rcd\Placeholder\ListStringPlaceholder;
use Drupal\farm_rcd\Placeholder\StringPlaceholder;
use Drupal\farm_rcd\RcdOptionLists;
use Drupal\plan\Entity\PlanInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * An event subscriber for the GenerateDocumentEvent.
 */
class GenerateDocumentEventSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      GenerateDocumentEvent::EVENT_NAME => ['onDocumentGenerate'],
    ];
  }

  /**
   * React to the GenerateDocumentEvent.
   *
   * @param \Drupal\farm_rcd\Event\GenerateDocumentEvent $event
   *   The GenerateDocumentEvent object.
   */
  public function onDocumentGenerate(GenerateDocumentEvent $event) {

    // Load the event context.
    $context = $event->getContext();

    // Only proceed if a test plan is included in the context.
    if (empty($context['plan']) || !($context['plan'] instanceof PlanInterface && $context['plan']->bundle() == 'rcd_rcp')) {
      return;
    }
    $plan = $context['plan'];

    // Build placeholders.
    $placeholders = [];

    // Load values from the farm organization associated with the plan (if
    // available).
    if (!$plan->get('farm')->isEmpty()) {
      $farm = $plan->get('farm')->referencedEntities()[0];
      $organization_label = $farm->label();
    }
    $placeholders[] = new StringPlaceholder('organization_label', $organization_label ?? '');

    // Load values from the property land assets associated with the plan (if
    // available).
    if (!$plan->get('property')->isEmpty()) {
      $property = $plan->get('property')->referencedEntities()[0];
      $property_label = $property->label();
      $property_description = $property->get('notes')->value;
      $property_apns = implode(', ', array_map(function ($value) {
        return $value['value'];
      }, $property->get('rcd_apn')->getValue()));
      $riparian_areas = $property->get('rcd_riparian_areas')->value;
      $wildlife = $property->get('rcd_wildlife')->value;
    }
    $placeholders[] = new StringPlaceholder('property_label', $property_label ?? '');
    $placeholders[] = new StringPlaceholder('property_description', $property_description ?? '');
    $placeholders[] = new StringPlaceholder('property_apns', $property_apns ?? '');
    $placeholders[] = new StringPlaceholder('riparian_areas', $riparian_areas ?? '');
    $placeholders[] = new StringPlaceholder('wildlife', $wildlife ?? '');

    // Load values from the intake log associated with the plan (if available).
    if (!$plan->get('intake')->isEmpty()) {
      $intake = $plan->get('intake')->referencedEntities()[0];

      // Stakeholder name and type.
      $intake_stakeholder_name = $intake->get('intake_stakeholder_name')->value;
      $intake_stakeholder_type = RcdOptionLists::stakeholderTypes()[$intake->get('intake_stakeholder_type')->value]->render();

      // Property owner and acreage.
      $intake_property_owner = $intake->get('intake_property_owner')->value;
      $intake_property_acreage = (string) ($intake->get('intake_property_acreage')->value + 0);

      // Disadvantaged groups.
      $socially_disadvantaged = implode(', ', array_map(function ($value) use ($intake) {
        if ($value['value'] == 'other') {
          return 'Other: ' . $intake->get('intake_stakeholder_group_other')->value;
        }
        return RcdOptionLists::stakeholderGroups()[$value['value']]->render();
      }, $intake->get('intake_stakeholder_group')->getValue()));

      // Land use.
      $intake_land_use = array_map(function ($value) {
        return RcdOptionLists::landUses()[$value['value']]->render();
      }, $intake->get('intake_property_use')->getValue());

      // Stakeholder goals.
      $intake_stakeholder_goals = array_map(function ($item) use ($intake) {
        $value = $item['value'];
        if ($value == 'other') {
          return 'Other: ' . $intake->get('intake_goals_other')->value;
        }
        return RcdOptionLists::goals()[$value]->render();
      }, $intake->get('intake_goals')->getValue());

      // Stakeholder concerns.
      $intake_stakeholder_concerns = array_map(function ($item) use ($intake) {
        $value = $item['value'];
        if ($value == 'other') {
          return 'Other: ' . $intake->get('intake_concerns_other')->value;
        }
        return RcdOptionLists::concerns()[$value]->render();
      }, $intake->get('intake_concerns')->getValue());
    }
    $placeholders[] = new StringPlaceholder('intake_stakeholder_name', $intake_stakeholder_name ?? '');
    $placeholders[] = new StringPlaceholder('intake_stakeholder_type', $intake_stakeholder_type ?? '');
    $placeholders[] = new StringPlaceholder('intake_property_owner', $intake_property_owner ?? '');
    $placeholders[] = new StringPlaceholder('intake_property_acreage', $intake_property_acreage ?? '');
    $placeholders[] = new StringPlaceholder('socially_disadvantaged', $socially_disadvantaged ?? '');
    $placeholders[] = new ListStringPlaceholder('intake_land_use', $intake_land_use ?? []);
    $placeholders[] = new ListStringPlaceholder('intake_stakeholder_goals', $intake_stakeholder_goals ?? []);
    $placeholders[] = new ListStringPlaceholder('intake_stakeholder_concerns', $intake_stakeholder_concerns ?? []);

    // Load values from practice implementation plans associated with the plan
    // to build a set of repeating blocks for each practice, within repeating
    // blocks for each location.
    if (!$plan->get('practice_implementation_plan')->isEmpty()) {

      // Load all practice plans, indexed by location (land asset).
      $practices_by_location = array_reduce($plan->get('practice_implementation_plan')->referencedEntities(), function ($carry, $plan) {
        $asset_id = $plan->get('land')->first()->target_id;
        $carry[$asset_id][] = $plan;
        return $carry;
      }, []);

      // Iterate through each location and build placeholders.
      $locations = [];
      foreach ($practices_by_location as $asset_id => $plans) {

        // Load the location land asset.
        $land_asset = $this->entityTypeManager->getStorage('asset')->load($asset_id);

        // Iterate through the practice plans and build placeholders.
        $location_practices = [];
        foreach ($plans as $plan) {
          $practice_info = ConservationPractices::get($plan->get('rcd_practice')->value);
          if (is_null($practice_info)) {
            continue;
          }
          $practice_name = $practice_info['label']->render();
          if (!empty($practice_info['nrcs_code'])) {
            $practice_name .= ' (NRCS code ' . $practice_info['nrcs_code'] . ')';
          }
          $practice_measurement = '';
          if (!empty($plan->get('rcd_acres')->value)) {
            $practice_measurement = ($plan->get('rcd_acres')->value + 0) . ' acres';
          }
          elseif (!empty($plan->get('rcd_linear_feet')->value)) {
            $practice_measurement = ($plan->get('rcd_linear_feet')->value + 0) . ' linear feet';
          }
          $location_practices[] = [
            new StringPlaceholder('practice_name', $practice_name),
            new StringPlaceholder('practice_overview', $plan->get('notes')->value ?? ''),
            new StringPlaceholder('practice_measurement', $practice_measurement),
            new ListStringPlaceholder('practice_benefits', $practice_info['benefits']),
            new ListStringPlaceholder('practice_resources', $practice_info['resources']),
          ];
        }

        // Build placeholders for each location.
        $locations[] = [
          new StringPlaceholder('location_name', $land_asset->label()),
          new StringPlaceholder('location_type', RcdOptionLists::landTypes()[$land_asset->get('land_type')->value]->render()),
          new StringPlaceholder('location_overview', $land_asset->get('notes')->value ?? ''),
          new ListBlockPlaceholder('location_practices', $location_practices),
        ];
      }
    }
    $placeholders[] = new ListBlockPlaceholder('locations', $locations ?? []);

    // Add placeholders to the event.
    $event->addPlaceholders($placeholders);
  }

}
