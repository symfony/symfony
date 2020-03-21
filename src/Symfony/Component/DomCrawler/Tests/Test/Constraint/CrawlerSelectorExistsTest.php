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

use Symfony\Component\DomCrawler\Test\Constraint\CrawlerSelectorExists;

class CrawlerSelectorExistsTest extends AbstractConstraintTest
{
    protected $errorMessage = 'Failed asserting that the Crawler matches selector "p".';

    protected function setUp(): void
    {
        $this->constraint = new CrawlerSelectorExists('p');
    }

    public function provideConstraintData()
    {
        yield ['<p/>', true];
        yield ['<p>Foo</p>', true];
        yield ['<h1>Foo</h1>', false];
    }
}
