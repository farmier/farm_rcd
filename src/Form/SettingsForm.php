<?php

declare(strict_types=1);

namespace Drupal\farm_rcd\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;

/**
 * Provides a settings form for the RCD module.
 */
class SettingsForm extends ConfigFormbase {

  use AutowireTrait;

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected FileSystemInterface $fileSystem,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'farm_rcd.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'farm_rcd_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    // Logo.
    $form['logo_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Logo path'),
      '#default_value' => $config->get('logo_path'),
    ];
    $form['logo_upload'] = [
      '#type' => 'file',
      '#title' => $this->t('Upload logo image'),
      '#upload_validators' => [
        'FileExtension' => [
          'extensions' => 'png gif jpg jpeg apng svg',
        ],
      ],
    ];

    // Intake notification email address.
    $form['intake_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Intake notification email'),
      '#description' => $this->t('This email address will be notified when a new intake is received.'),
      '#default_value' => $config->get('intake_email'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Check for a new uploaded logo and put the temporary file in form values,
    // so we can save it on submit.
    $file = _file_save_upload_from_form($form['logo_upload'], $form_state, 0);
    if ($file) {
      $form_state->setValue('logo_upload', $file);
    }

    // If the user provided a path for a logo file, make sure a file exists
    // at that path.
    if ($form_state->getValue('logo_path')) {
      $path = $this->validatePath($form_state->getValue('logo_path'));
      if (!$path) {
        $form_state->setErrorByName('logo_path', $this->t('The logo path is invalid.'));
      }
    }
  }

  /**
   * Helper function for validating a file path.
   *
   * This is copied directly from ThemeSettingsForm::validatePath().
   *
   * @param string $path
   *   A path relative to the Drupal root or to the public files directory, or
   *   a stream wrapper URI.
   *
   * @return mixed
   *   A valid path that can be displayed through the theme system, or FALSE if
   *   the path could not be validated.
   */
  protected function validatePath($path) {

    // Absolute local file paths are invalid.
    if ($this->fileSystem->realpath($path) == $path) {
      return FALSE;
    }

    // A path relative to the Drupal root or a fully qualified URI is valid.
    if (is_file($path)) {
      return $path;
    }

    // Prepend 'public://' for relative file paths within public filesystem.
    if (StreamWrapperManager::getScheme($path) === FALSE) {
      $path = 'public://' . $path;
    }
    if (is_file($path)) {
      return $path;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // If the user uploaded a new logo, save it to a permanent location.
    try {
      if (!empty($values['logo_upload'])) {
        $filename = $this->fileSystem->copy($values['logo_upload']->getFileUri(), 'public://');
        $values['logo_path'] = $filename;
      }
    }
    catch (FileException) {
      // Ignore.
    }
    unset($values['logo_upload']);

    // If the user entered a path relative to the system files directory for a
    // logo, store a public:// URI so the theme system can handle it.
    if (!empty($values['logo_path'])) {
      $values['logo_path'] = $this->validatePath($values['logo_path']);
    }

    // Save to config.
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('logo_path', $values['logo_path'])
      ->set('intake_email', $values['intake_email'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
