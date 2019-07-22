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
        $constraint = new CrawlerSelectorTextContains('title', 'Foo');
        $this->assertTrue($constraint->evaluate(new Crawler('<html><head><title>Foobar'), '', true));
        $this->assertFalse($constraint->evaluate(new Crawler('<html><head><title>Bar'), '', true));

        try {
            $constraint->evaluate(new Crawler('<html><head><title>Bar'));
        } catch (ExpectationFailedException $e) {
            $this->assertEquals("Failed asserting that the Crawler has a node matching selector \"title\" with content containing \"Foo\".\n", TestFailure::exceptionToString($e));

            return;
        }

        $this->fail();
    }
}
