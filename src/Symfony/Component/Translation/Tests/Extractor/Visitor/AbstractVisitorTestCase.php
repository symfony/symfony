<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
    protected function extract(NodeVisitor $visitor, string|iterable $resource): MessageCatalogue
    {
        $extractor = new PhpAstExtractor([$visitor]);
        $extractor->setPrefix('prefix');

        $catalogue = new MessageCatalogue('en');

        $extractor->extract($resource, $catalogue);

        return $catalogue;
    }
}
