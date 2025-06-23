<?php

namespace Symfony\Component\Translation\Tests\Extractor\Visitor;

use PhpParser\NodeVisitor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Extractor\PhpAstExtractor;
use Symfony\Component\Translation\MessageCatalogue;

abstract class AbstractVisitorTestCase extends TestCase
{
    /**
     * @param string|iterable<string> $resource Files, a file or a directory
     */
    public function extract(NodeVisitor $visitor, string|iterable $resource): MessageCatalogue
    {
        $extractor = new PhpAstExtractor([$visitor]);
        $extractor->setPrefix('prefix');

        $catalogue = new MessageCatalogue('en');

        $extractor->extract($resource, $catalogue);

        return $catalogue;
    }
}
