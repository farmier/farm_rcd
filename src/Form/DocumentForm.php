<?php

declare(strict_types=1);

namespace Drupal\farm_rcd\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\farm_rcd\DocumentGeneratorInterface;
use Drupal\file\FileInterface;
use Drupal\plan\Entity\PlanInterface;

/**
 * Document form.
 */
class DocumentForm extends PlanningWorkflowFormBase {

  use AutowireTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected DocumentGeneratorInterface $documentGenerator,
    protected FileSystemInterface $fileSystem,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
  ) {
    parent::__construct($this->entityTypeManager);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'farm_rcd_document_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?PlanInterface $plan = NULL) {
    $form = parent::buildForm($form, $form_state, $plan);

    $form['document'] = [
      '#type' => 'details',
      '#title' => $this->t('Prepare Document'),
      '#description' => $this->t('Use this form to generate and upload documents for this plan. This will take all of the information in the associated records above and use it to generate a draft document from a template. This document can then be downloaded, modified, and re-uploaded for storage purposes.'),
    ];

    // Open if the status is "planning" and there are conservation practice
    // plans associated with the plan, but no files are attached.
    $form['document']['#open'] = $this->plan->get('status')->value == 'planning' && !empty($this->practicePlans) && $this->plan->get('file')->isEmpty();

    // Do not open if the "open" query parameter is set.
    // @todo https://github.com/farmier/farm_rcd/issues/57
    if ($this->getRequest()->query->has('open')) {
      $form['document']['#open'] = FALSE;
    }

    // Generate document button.
    $form['document']['generate'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate document from template'),
      '#submit' => [[$this, 'submitGenerate']],
    ];

    // Upload finished document.
    $form['document']['upload'] = [
      '#type' => 'file',
      '#title' => $this->t('Upload document'),
      '#description' => $this->t('Use this to upload the finished document.'),
      '#upload_validators' => [
        'FileExtension' => [
          'extensions' => 'docx odt pdf',
        ],
      ],
    ];

    // Save documents button.
    $form['document']['actions'] = [
      '#type' => 'actions',
      '#weight' => 1000,
    ];
    $form['document']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save documents'),
    ];

    return $form;
  }

  /**
   * Submit function for generating a document from template.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitGenerate(array &$form, FormStateInterface $form_state) {
    try {

      // Get the document template path.
      $template_path = $this->moduleHandler->getModule('farm_rcd')->getPath() . '/templates/default-rcp-template.docx';

      // Generate a filename.
      $filename = strtolower(trim(preg_replace('#\W+#', '-', $this->plan->label()), '-')) . '.docx';

      // Add the plan to the document generation context.
      $context = [
        'plan' => $this->plan,
      ];

      // Generate the report.
      $file = $this->documentGenerator->generate($template_path, $filename, $context);

      // Show a link to the file.
      $url = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
      $this->messenger()->addMessage($this->t('Document created: <a href=":url">%filename</a>', [
        ':url' => $url,
        '%filename' => $file->label(),
      ]));
    }
    catch (\Exception $e) {
      $this->messenger()->addWarning($this->t('Document generation failed. @error', ['@error' => $e->getMessage()]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Only allow uploading documents if the plan status is planning.
    $status = $this->plan->get('status')->value;
    if (!empty($form_state->getValue(['document', 'upload'])) && $status != 'planning') {
      $form_state->setError($form['document']['upload'], $this->t('Documents can only be uploaded to plans that are in the planning stage. This plan has been marked as @status.', ['@status' => $status]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Process uploaded file.
    if (!empty($form_state->getValue(['document', 'upload']))) {

      // Prepare the private://rcp/[date] directory.
      $directory = 'private://rcp/' . date('Y-m-d');
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

      // Save the uploaded file.
      $validators = ['FileExtension' => ['extensions' => 'docx odt pdf']];
      $file = file_save_upload('document', $validators, $directory, 0, FileExists::Rename);

      // Show an error if upload failed.
      if (!($file instanceof FileInterface)) {
        $this->messenger()->addError('The file could not be uploaded.');
        return;
      }

      // Attach the file to the plan and save a revision log message.
      $this->plan->get('file')->appendItem($file);
      $this->plan->setRevisionLogMessage((string) new FormattableMarkup('Document uploaded: <a href=":uri">' . $file->label() . '</a>.', [':uri' => $this->fileUrlGenerator->generateString($file->getFileUri()), '@label' => $file->label()]));
      $this->plan->save();

      // Show a message.
      $this->messenger()->addMessage($this->t('Document uploaded.'));
    }

    // Otherwise, show a warning.
    else {
      $this->messenger()->addWarning($this->t('No document uploaded.'));
    }
  }

}
