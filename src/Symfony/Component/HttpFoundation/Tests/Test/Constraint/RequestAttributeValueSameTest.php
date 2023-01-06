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

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestFailure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Test\Constraint\RequestAttributeValueSame;

class RequestAttributeValueSameTest extends TestCase
{
    public function testConstraint()
    {
        $request = new Request();
        $request->attributes->set('foo', 'bar');
        $constraint = new RequestAttributeValueSame('foo', 'bar');
        $this->assertTrue($constraint->evaluate($request, '', true));
        $constraint = new RequestAttributeValueSame('bar', 'foo');
        $this->assertFalse($constraint->evaluate($request, '', true));

        try {
            $constraint->evaluate($request);
        } catch (ExpectationFailedException $e) {
            $this->assertEquals("Failed asserting that the Request has attribute \"bar\" with value \"foo\".\n", TestFailure::exceptionToString($e));

            return;
        }

        $this->fail();
    }
}
