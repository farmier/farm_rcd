<?php

declare(strict_types=1);

namespace Drupal\Tests\farm_rcd\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\farm_rcd\Traits\PhpWordTestingTrait;
use PhpOffice\PhpWord\IOFactory;

/**
 * Tests for the document generator service.
 */
class DocumentGeneratorTest extends KernelTestBase {

  use PhpWordTestingTrait;

  /**
   * Document generator service.
   *
   * @var \Drupal\farm_rcd\DocumentGeneratorInterface
   */
  protected $documentGenerator;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'asset',
    'entity',
    'farm_entity',
    'farm_farm',
    'farm_field',
    'farm_land',
    'farm_log',
    'farm_map',
    'farm_rcd',
    'farm_rcd_test',
    'file',
    'geofield',
    'log',
    'options',
    'organization',
    'plan',
    'state_machine',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('file');
    $this->installEntitySchema('plan');
    $this->installConfig([
      'farm_rcd_test',
    ]);
    $this->documentGenerator = \Drupal::service('rcd.document.generator');
  }

  /**
   * Test the document generator service.
   */
  public function testDocumentGenerator() {

    // Generate a document from the test template.
    $template_path = \Drupal::moduleHandler()->getModule('farm_rcd_test')->getPath() . '/templates/template.docx';
    $filename = 'test-filename.docx';
    $file = $this->documentGenerator->generate($template_path, $filename);
    $default_schema = \Drupal::configFactory()->get('system.file')->get('default_scheme');
    $this->assertEquals($default_schema . '://docs/test-filename.docx', $file->getFileUri());
    $this->assertEquals('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $file->getMimeType());

    // Get the real path to the file.
    $real_path = \Drupal::service('stream_wrapper_manager')->getViaUri($file->getFileUri())->realpath();

    // Copy to a temporary file because ZipArchive can't read vfs:// streams.
    $temp_path = tempnam(sys_get_temp_dir(), 'doc');
    copy($real_path, $temp_path);

    // Load the file with PhpWord and confirm that it contains expected text.
    $doc = IOFactory::load($temp_path);

    // Confirm that simple string replacement works.
    $this->assertDocNotContainsText($doc, '${string}');
    $this->assertDocContainsText($doc, 'Replaced String');

    // Confirm that heading string replacement works.
    $this->assertDocNotContainsText($doc, '${heading_string}');
    $this->assertDocContainsText($doc, 'Replaced Heading String');

    // Confirm that simple bulleted list item replacement works.
    $this->assertDocNotContainsText($doc, '${list}');
    $this->assertDocNotContainsText($doc, '${item}');
    $this->assertDocContainsText($doc, 'Replaced List Item 1');
    $this->assertDocContainsText($doc, 'Replaced List Item 2');

    // Confirm that strings inside a repeating block work.
    $this->assertDocNotContainsText($doc, '${block_strings}');
    $this->assertDocNotContainsText($doc, '${block_string}');
    $this->assertDocContainsText($doc, 'Replaced Block String 1');
    $this->assertDocContainsText($doc, 'Replaced Block String 2');

    // Confirm bulleted lists inside a repeating block work.
    $this->assertDocNotContainsText($doc, '${block_lists}');
    $this->assertDocNotContainsText($doc, '${block_list}');
    $this->assertDocNotContainsText($doc, '${item}');
    $this->assertDocContainsText($doc, 'Replaced Block List Item 1');
    $this->assertDocContainsText($doc, 'Replaced Block List Item 2');
    $this->assertDocContainsText($doc, 'Replaced Block List Item 3');
    $this->assertDocContainsText($doc, 'Replaced Block List Item 4');

    // Confirm repeating blocks inside a repeating block work.
    $this->assertDocNotContainsText($doc, '${block_blocks}');
    $this->assertDocNotContainsText($doc, '${block_block}');
    $this->assertDocNotContainsText($doc, '${block_block_heading_string}');
    $this->assertDocNotContainsText($doc, '${block_block_string}');
    $this->assertDocNotContainsText($doc, '${block_block_list}');
    $this->assertDocNotContainsText($doc, '${item}');
    $this->assertDocContainsText($doc, 'Replaced Nested Block Heading String 1');
    $this->assertDocContainsText($doc, 'Replaced Nested Block Heading String 2');
    $this->assertDocContainsText($doc, 'Replaced Nested Block Heading String 3');
    $this->assertDocContainsText($doc, 'Replaced Nested Block Heading String 4');
    $this->assertDocContainsText($doc, 'Replaced Nested Block String 1');
    $this->assertDocContainsText($doc, 'Replaced Nested Block String 2');
    $this->assertDocContainsText($doc, 'Replaced Nested Block String 3');
    $this->assertDocContainsText($doc, 'Replaced Nested Block String 4');
    $this->assertDocContainsText($doc, 'Replaced Nested Block List Item 1');
    $this->assertDocContainsText($doc, 'Replaced Nested Block List Item 2');
    $this->assertDocContainsText($doc, 'Replaced Nested Block List Item 3');
    $this->assertDocContainsText($doc, 'Replaced Nested Block List Item 4');
    $this->assertDocContainsText($doc, 'Replaced Nested Block List Item 5');
    $this->assertDocContainsText($doc, 'Replaced Nested Block List Item 6');
    $this->assertDocContainsText($doc, 'Replaced Nested Block List Item 7');
    $this->assertDocContainsText($doc, 'Replaced Nested Block List Item 8');

    // Delete the temporary document file.
    unlink($temp_path);
  }

}
