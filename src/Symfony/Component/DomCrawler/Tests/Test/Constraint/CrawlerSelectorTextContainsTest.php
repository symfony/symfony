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
    public function testConstraint()
    {
        $constraint = new CrawlerSelectorTextContains('title', 'Foo');
        self::assertTrue($constraint->evaluate(new Crawler('<html><head><title>Foobar'), '', true));
        self::assertFalse($constraint->evaluate(new Crawler('<html><head><title>Bar'), '', true));
        self::assertFalse($constraint->evaluate(new Crawler('<html><head></head><body>Bar'), '', true));

        try {
            $constraint->evaluate(new Crawler('<html><head><title>Bar'));

            self::fail();
        } catch (ExpectationFailedException $e) {
            self::assertEquals("Failed asserting that the text \"Bar\" of the node matching selector \"title\" contains \"Foo\".\n", TestFailure::exceptionToString($e));
        }

        try {
            $constraint->evaluate(new Crawler('<html><head></head><body>Bar'));

            self::fail();
        } catch (ExpectationFailedException $e) {
            self::assertEquals("Failed asserting that the Crawler has a node matching selector \"title\".\n", TestFailure::exceptionToString($e));
        }
    }
}
