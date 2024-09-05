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
use Symfony\Component\DomCrawler\Test\Constraint\CrawlerSelectorAttributeValueSame;

class CrawlerSelectorAttributeValueSameTest extends TestCase
{
    public function testConstraint()
    {
        $constraint = new CrawlerSelectorAttributeValueSame('input[name="username"]', 'value', 'Fabien');
        $this->assertTrue($constraint->evaluate(new Crawler('<html><body><form><input type="text" name="username" value="Fabien">'), '', true));
        $this->assertFalse($constraint->evaluate(new Crawler('<html><body><form><input type="text" name="username">'), '', true));
        $this->assertFalse($constraint->evaluate(new Crawler('<html><head><title>Bar'), '', true));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('Failed asserting that the Crawler has a node matching selector "input[name="username"]" with attribute "value" of value "Fabien".');

        $constraint->evaluate(new Crawler('<html><head><title>Bar'));
    }
}
