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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseHeaderSame;

class ResponseHeaderSameTest extends TestCase
{
    public function testResponseHeaderSameWithExpectedHeaderValueIsSame()
    {
        $constraint = new ResponseHeaderSame('X-Token', 'custom-token');

        $response = new Response();
        $response->headers->set('X-Token', 'custom-token');

        $this->assertTrue($constraint->evaluate($response, '', true));
    }

    public function testResponseHeaderSameWithExpectedHeaderValueIsDifferent()
    {
        $constraint = new ResponseHeaderSame('X-Token', 'custom-token');

        $response = new Response();
        $response->headers->set('X-Token', 'default-token');

        $this->assertFalse($constraint->evaluate($response, '', true));

        try {
            $constraint->evaluate($response);
        } catch (ExpectationFailedException $e) {
            $this->assertEquals("Failed asserting that the Response has header \"X-Token\" with value \"custom-token\", value of header \"X-Token\" is \"default-token\".\n", TestFailure::exceptionToString($e));

            return;
        }

        $this->fail();
    }

    public function testResponseHeaderSameWithExpectedHeaderIsNotPresent()
    {
        $constraint = new ResponseHeaderSame('X-Token', 'custom-token');

        $response = new Response();

        $this->assertFalse($constraint->evaluate($response, '', true));

        try {
            $constraint->evaluate($response);
        } catch (ExpectationFailedException $e) {
            $this->assertEquals("Failed asserting that the Response has header \"X-Token\" with value \"custom-token\", header \"X-Token\" is not set.\n", TestFailure::exceptionToString($e));

            return;
        }

        $this->fail();
    }
}
