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

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestFailure;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Test\Constraint\CrawlerSelectorTextContains;

class CrawlerSelectorTextContainsTest extends TestCase
{
    public function testConstraint(): void
    {
        $constraint = new CrawlerSelectorTextContains('table td', 'Foo');
        $this->assertTrue($constraint->evaluate(new Crawler('<html><body><table><tr><td>Bar</td></tr><tr><td>Foobar</td></tr>'), '', true));
        $this->assertTrue($constraint->evaluate(new Crawler('<html><body><table><tr><td>Foobar</td></tr><tr><td>Bar</td></tr>'), '', true));
        $this->assertFalse($constraint->evaluate(new Crawler('<html><body><table><tr><td>Fuubar</td></tr><tr><td>Bar</td></tr>'), '', true));

        try {
            $constraint->evaluate(new Crawler('<html><head><title>Bar'));
        } catch (ExpectationFailedException $e) {
            $this->assertEquals("Failed asserting that the Crawler has a node matching selector \"table td\" with content containing \"Foo\".\n", TestFailure::exceptionToString($e));

            return;
        }

        $this->fail();
    }
}
