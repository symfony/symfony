<?php

namespace Symfony\Component\Translation\Tests\Extractor\Visitor;

use Symfony\Component\Translation\Extractor\Visitor\ConstraintVisitor;

final class ConstraintVisitorTest extends AbstractVisitorTestCase
{
    private const FIXTURES_FOLDER = __DIR__ . '/../../Fixtures/extractor-php-ast/constraint-visitor/';

    public function testExtractMessages()
    {
        $catalogue = $this->extract(new ConstraintVisitor(['NotBlank', 'Isbn', 'Length']), self::FIXTURES_FOLDER);

        $this->assertEquals(
            [
                'validators' => [
                    'message-in-constraint-attribute' => 'prefixmessage-in-constraint-attribute',
                    // 'custom Isbn message from attribute' => 'prefixcustom Isbn message from attribute',
                    'custom Isbn message from attribute with options as array' => 'prefixcustom Isbn message from attribute with options as array',
                    'custom Length exact message from attribute from named argument' => 'prefixcustom Length exact message from attribute from named argument',
                    'custom Length exact message from attribute from named argument 1/2' => 'prefixcustom Length exact message from attribute from named argument 1/2',
                    'custom Length min message from attribute from named argument 2/2' => 'prefixcustom Length min message from attribute from named argument 2/2',
                    // 'custom Isbn message' => 'prefixcustom Isbn message',
                    'custom Isbn message with options as array' => 'prefixcustom Isbn message with options as array',
                    'custom Isbn message from named argument' => 'prefixcustom Isbn message from named argument',
                    'custom Length exact message from named argument' => 'prefixcustom Length exact message from named argument',
                    'custom Length exact message from named argument 1/2' => 'prefixcustom Length exact message from named argument 1/2',
                    'custom Length min message from named argument 2/2' => 'prefixcustom Length min message from named argument 2/2',
                ],
            ],
            $catalogue->all(),
        );

        $this->assertEquals(['sources' => [self::FIXTURES_FOLDER . 'validator-constraints.php:7']], $catalogue->getMetadata('message-in-constraint-attribute', 'validators'));
    }
}
