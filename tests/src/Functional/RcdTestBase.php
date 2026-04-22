<?php

declare(strict_types=1);

namespace Drupal\Tests\farm_rcd\Functional;

use Drupal\Tests\farm_test\Functional\FarmBrowserTestBase;

/**
 * Base class for RCD functional tests.
 */
abstract class RcdTestBase extends FarmBrowserTestBase {

  /**
   * Test user.
   *
   * @var \Drupal\user\Entity\User|bool
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'gin';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'farm_rcd',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create and login a user with the Staff role.
    $this->user = $this->createUser();
    $this->user->addRole('rcd_staff');
    $this->user->save();
    $this->drupalLogin($this->user);
  }

}
