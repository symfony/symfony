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

use Symfony\Component\DomCrawler\Test\Constraint\CrawlerSelectorAttributeValueSame;

class CrawlerSelectorAttributeValueSameTest extends AbstractConstraintTest
{
    protected $errorMessage = 'Failed asserting that the Crawler has a node matching selector "input[name="username"]" with attribute "value" of value "Fabien".';

    protected function setUp(): void
    {
        $this->constraint = new CrawlerSelectorAttributeValueSame('input[name="username"]', 'value', 'Fabien');
    }

    public function provideConstraintData()
    {
        yield ['<input type="text" name="username" value="Fabien">', true];

        yield ['<input type="text" name="username" value="Wouter">', false];
        yield ['<h1>Bar</h1>', false];
    }
}
