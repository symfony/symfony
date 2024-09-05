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
    }

    public function testDoesNotMatchIfNodeDoesContainExpectedText()
    {
        $constraint = new CrawlerAnySelectorTextContains('ul li', 'Foo');

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('Failed asserting that the text of any node matching selector "ul li" contains "Foo".');

        $constraint->evaluate(new Crawler('<ul><li>Bar</li><li>Baz'));
    }

    public function testDoesNotMatchIfNodeDoesNotExist()
    {
        $constraint = new CrawlerAnySelectorTextContains('ul li', 'Foo');

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('Failed asserting that the Crawler has a node matching selector "ul li".');

        $constraint->evaluate(new Crawler('<html><head><title>Foobar'));
    }
}
