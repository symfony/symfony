<?php

namespace Symfony\Component\Translation\Tests\Extractor\Visitor;

use PhpParser\NodeVisitor;
use Symfony\Component\Translation\Extractor\Visitor\ConstraintVisitor;
use Symfony\Component\Translation\MessageCatalogue;

class ConstraintVisitorTest extends AbstractVisitorTest
{
    private const FIXTURES_FOLDER = __DIR__ . '/../../Fixtures/extractor-php-ast/constraint-visitor/';

    public function getVisitor(): NodeVisitor
    {
        return new ConstraintVisitor(['NotBlank', 'Isbn', 'Length']);
    }

    public function getResource(): iterable|string
    {
        return self::FIXTURES_FOLDER;
    }

    public function assertCatalogue(MessageCatalogue $catalogue): void
    {
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

        $this->assertEquals(['sources' => [self::FIXTURES_FOLDER . 'validator-constraints.php:8']], $catalogue->getMetadata('message-in-constraint-attribute', 'validators'));
    }
}
