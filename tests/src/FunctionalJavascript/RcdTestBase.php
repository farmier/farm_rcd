<?php

declare(strict_types=1);

namespace Drupal\Tests\farm_rcd\FunctionalJavascript;

use Drupal\Tests\farm_test\FunctionalJavascript\FarmWebDriverTestBase;

/**
 * Base class for RCD functional Javascript tests.
 */
abstract class RcdTestBase extends FarmWebDriverTestBase {

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
