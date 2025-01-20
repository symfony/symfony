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
use Symfony\Component\DomCrawler\Test\Constraint\CrawlerSelectorTextContains;

class CrawlerSelectorTextContainsTest extends TestCase
{
    public function testConstraint()
    {
        $constraint = new CrawlerSelectorTextContains('title', 'Foo');
        $this->assertTrue($constraint->evaluate(new Crawler('<html><head><title>Foobar'), '', true));
        $this->assertFalse($constraint->evaluate(new Crawler('<html><head><title>Bar'), '', true));
        $this->assertFalse($constraint->evaluate(new Crawler('<html><head></head><body>Bar'), '', true));
    }

    public function testDoesNotMatchIfNodeTextIsNotExpectedValue()
    {
        $constraint = new CrawlerSelectorTextContains('title', 'Foo');

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('Failed asserting that the text "Bar" of the node matching selector "title" contains "Foo".');

        $constraint->evaluate(new Crawler('<html><head><title>Bar'));
    }

    public function testDoesNotMatchIfNodeDoesNotExist()
    {
        $constraint = new CrawlerSelectorTextContains('title', 'Foo');

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('Failed asserting that the Crawler has a node matching selector "title".');

        $constraint->evaluate(new Crawler('<html><head></head><body>Bar'));
    }
}
