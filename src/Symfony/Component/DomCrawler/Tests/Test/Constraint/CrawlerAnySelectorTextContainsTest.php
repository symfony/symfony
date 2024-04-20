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
use Symfony\Component\DomCrawler\Test\Constraint\CrawlerAnySelectorTextContains;

class CrawlerAnySelectorTextContainsTest extends TestCase
{
    public function testConstraint()
    {
        $constraint = new CrawlerAnySelectorTextContains('ul li', 'Foo');

        self::assertTrue($constraint->evaluate(new Crawler('<ul><li>Foo</li>'), '', true));
        self::assertTrue($constraint->evaluate(new Crawler('<ul><li>Bar</li><li>Foo'), '', true));
        self::assertTrue($constraint->evaluate(new Crawler('<ul><li>Bar</li><li>Foo Bar Baz'), '', true));
        self::assertFalse($constraint->evaluate(new Crawler('<ul><li>Bar</li><li>Baz'), '', true));

        try {
            $constraint->evaluate(new Crawler('<ul><li>Bar</li><li>Baz'));

            self::fail();
        } catch (ExpectationFailedException $e) {
            self::assertEquals("Failed asserting that the text of any node matching selector \"ul li\" contains \"Foo\".\n", TestFailure::exceptionToString($e));
        }

        try {
            $constraint->evaluate(new Crawler('<html><head><title>Foobar'));

            self::fail();
        } catch (ExpectationFailedException $e) {
            self::assertEquals("Failed asserting that the Crawler has a node matching selector \"ul li\".\n", TestFailure::exceptionToString($e));

            return;
        }
    }
}
