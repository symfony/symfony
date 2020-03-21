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

abstract class AbstractConstraintTest extends TestCase
{
    protected $errorMessage;
    protected $constraint;

    /**
     * @dataProvider provideConstraintData
     */
    public function testConstraint(string $html, bool $result)
    {
        if (!$result) {
            $this->expectException(ExpectationFailedException::class);
            $this->expectExceptionMessage($this->errorMessage);

            $this->constraint->evaluate(new Crawler($html));
        } else {
            $this->assertTrue($this->constraint->evaluate(new Crawler($html), '', true));
        }
    }

    abstract public function provideConstraintData();
}
