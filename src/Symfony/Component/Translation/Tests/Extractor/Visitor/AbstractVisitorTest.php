<?php

namespace Symfony\Component\Translation\Tests\Extractor\Visitor;

use PhpParser\NodeVisitor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Extractor\PhpAstExtractor;
use Symfony\Component\Translation\MessageCatalogue;

abstract class AbstractVisitorTest extends TestCase
{
    abstract public function getVisitor(): NodeVisitor;
    abstract public function getResource(): iterable|string;
    abstract public function assertCatalogue(MessageCatalogue $catalogue): void;

    public function testVisitor()
    {
        $extractor = new PhpAstExtractor([$this->getVisitor()]);
        $extractor->setPrefix('prefix');
        $catalogue = new MessageCatalogue('en');

        $extractor->extract($this->getResource(), $catalogue);

        $this->assertCatalogue($catalogue);

    }
}
