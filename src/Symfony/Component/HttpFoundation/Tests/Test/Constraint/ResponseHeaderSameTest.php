<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Test\Constraint;

use PHPUnit\Framework\Constraint\LogicalNot;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseHeaderSame;

class ResponseHeaderSameTest extends TestCase
{
    public function testConstraint()
    {
        $constraint = new ResponseHeaderSame('Cache-Control', 'no-cache, private');
        $this->assertTrue($constraint->evaluate(new Response(), '', true));
        $constraint = new ResponseHeaderSame('Cache-Control', 'public');
        $this->assertFalse($constraint->evaluate(new Response(), '', true));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('Failed asserting that the Response has header "Cache-Control" with value "public", value of header "Cache-Control" is "no-cache, private".');

        $constraint->evaluate(new Response());
    }

    public function testConstraintReportsMissingHeader()
    {
        $constraint = new ResponseHeaderSame('X-Missing', 'expected');

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('Failed asserting that the Response has header "X-Missing" with value "expected", header "X-Missing" is not set.');

        $constraint->evaluate(new Response());
    }

    public function testNegatedConstraintDoesNotRepeatTheValue()
    {
        $constraint = new LogicalNot(new ResponseHeaderSame('Cache-Control', 'no-cache, private'));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('Failed asserting that the Response does not have header "Cache-Control" with value "no-cache, private".');

        $constraint->evaluate(new Response());
    }
}
