<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Tests\Test\Constraint;

use Symfony\Component\DomCrawler\Test\Constraint\CrawlerSelectorTextContains;

class CrawlerSelectorTextContainsTest extends AbstractConstraintTest
{
    protected $errorMessage = 'Failed asserting that the Crawler has a node matching selector "p" with content containing "Foo".';

    protected function setUp(): void
    {
        $this->constraint = new CrawlerSelectorTextContains('p', 'Foo');
    }

    public function provideConstraintData()
    {
        yield ['<p>Foo</p>', true];
        yield ['<p>Foobar</p>', true];
        yield ['<p>Bar</p><p>Foo</p>', true];

        yield ['<p>Bar</p>', false];
        yield ['<h1>Foo</h1>', false];
    }
}
