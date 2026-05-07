<?php

declare(strict_types=1);

namespace Drupal\farm_rcd_document_generator_test\Plugin\Plan\PlanType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\farm_entity\Attribute\PlanType;
use Drupal\farm_entity\Plugin\Plan\PlanType\FarmPlanType;

/**
 * Provides a test plan type.
 */
#[PlanType(
  id: 'test',
  label: new TranslatableMarkup('Test plan'),
)]
class TestPlan extends FarmPlanType {

}
