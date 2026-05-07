<?php

declare(strict_types=1);

namespace Drupal\farm_rcd_document_generator_test\EventSubscriber;

use Drupal\farm_rcd\Event\GenerateDocumentEvent;
use Drupal\farm_rcd\Placeholder\ListBlockPlaceholder;
use Drupal\farm_rcd\Placeholder\ListStringPlaceholder;
use Drupal\farm_rcd\Placeholder\StringPlaceholder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * An event subscriber for the GenerateDocumentEvent.
 */
class GenerateDocumentEventSubscriber implements EventSubscriberInterface {

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

    // Build placeholders.
    $placeholders = [];

    // Simple string.
    $placeholders[] = new StringPlaceholder('string', 'Replaced String');

    // Heading string.
    $placeholders[] = new StringPlaceholder('heading_string', 'Replaced Heading String');

    // Simple bulleted list of string.
    $placeholders[] = new ListStringPlaceholder('list', [
      'Replaced List Item 1',
      'Replaced List Item 2',
    ]);

    // Strings inside a repeating block.
    $placeholders[] = new ListBlockPlaceholder('block_strings', [
      [
        new StringPlaceholder('block_string', 'Replaced Block String 1'),
      ],
      [
        new StringPlaceholder('block_string', 'Replaced Block String 2'),
      ],
    ]);

    // Lists inside a repeating block.
    $placeholders[] = new ListBlockPlaceholder('block_lists', [
      [
        new ListStringPlaceholder('block_list', [
          'Replaced Block List Item 1',
          'Replaced Block List Item 2',
        ]),
      ],
      [
        new ListStringPlaceholder('block_list', [
          'Replaced Block List Item 3',
          'Replaced Block List Item 4',
        ]),
      ],
    ]);

    // Repeating blocks inside a repeating block.
    $placeholders[] = new ListBlockPlaceholder('block_blocks', [
      [
        new ListBlockPlaceholder('block_block', [
          [
            new StringPlaceholder('block_block_heading_string', 'Replaced Nested Block Heading String 1'),
            new StringPlaceholder('block_block_string', 'Replaced Nested Block String 1'),
            new ListStringPlaceholder('block_block_list', [
              'Replaced Nested Block List Item 1',
              'Replaced Nested Block List Item 2',
            ]),
          ],
          [
            new StringPlaceholder('block_block_heading_string', 'Replaced Nested Block Heading String 2'),
            new StringPlaceholder('block_block_string', 'Replaced Nested Block String 2'),
            new ListStringPlaceholder('block_block_list', [
              'Replaced Nested Block List Item 3',
              'Replaced Nested Block List Item 4',
            ]),
          ],
        ]),
      ],
      [
        new ListBlockPlaceholder('block_block', [
          [
            new StringPlaceholder('block_block_heading_string', 'Replaced Nested Block Heading String 3'),
            new StringPlaceholder('block_block_string', 'Replaced Nested Block String 3'),
            new ListStringPlaceholder('block_block_list', [
              'Replaced Nested Block List Item 5',
              'Replaced Nested Block List Item 6',
            ]),
          ],
          [
            new StringPlaceholder('block_block_heading_string', 'Replaced Nested Block Heading String 4'),
            new StringPlaceholder('block_block_string', 'Replaced Nested Block String 4'),
            new ListStringPlaceholder('block_block_list', [
              'Replaced Nested Block List Item 7',
              'Replaced Nested Block List Item 8',
            ]),
          ],
        ]),
      ],
    ]);

    // Add placeholders to the event.
    $event->addPlaceholders($placeholders);
  }

}
