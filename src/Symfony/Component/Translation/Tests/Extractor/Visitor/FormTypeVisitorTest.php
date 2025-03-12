<?php

namespace Symfony\Component\Translation\Tests\Extractor\Visitor;

use PhpParser\NodeVisitor;
use Symfony\Component\Translation\Extractor\Visitor\FormTypeVisitor;
use Symfony\Component\Translation\Extractor\Visitor\TransMethodVisitor;
use Symfony\Component\Translation\MessageCatalogue;

class FormTypeVisitorTest extends AbstractVisitorTest
{
    private const FIXTURES_FOLDER = __DIR__ . '/../../Fixtures/extractor-php-ast/form-type-visitor/';

    public function getVisitor(): FormTypeVisitor
    {
        return new FormTypeVisitor();
    }

    public function getResource(): iterable|string
    {
        return self::FIXTURES_FOLDER;
    }

    public function assertCatalogue(MessageCatalogue $catalogue): void
    {
        $this->assertEquals(
            [
                'messages' => [
                    'label.foo1' => 'prefixlabel.foo1',
                    'label.find1' => 'prefixlabel.find1',
                    'find2' => 'prefixfind2',
                    'FOUND3' => 'prefixFOUND3',
                    'label.find4' => 'prefixlabel.find4',
                    'label.find5' => 'prefixlabel.find5',
                    'find6' => 'prefixfind6',
                    'bigger_find7' => 'prefixbigger_find7',
                    'camelFind8' => 'prefixcamelFind8',
                    'label.find9' => 'prefixlabel.find9',
                ],
            ],
            $catalogue->all(),
        );

        $this->assertEquals(['sources' => [self::FIXTURES_FOLDER . 'form-type.php:27']], $catalogue->getMetadata('label.find1'));
    }
}
