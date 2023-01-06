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
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseCookieValueSame;

class ResponseCookieValueSameTest extends TestCase
{
    public function testConstraint()
    {
        $response = new Response();
        $response->headers->setCookie(Cookie::create('foo', 'bar', 0, '/path'));
        $constraint = new ResponseCookieValueSame('foo', 'bar', '/path');
        $this->assertTrue($constraint->evaluate($response, '', true));
        $constraint = new ResponseCookieValueSame('foo', 'bar', '/path');
        $this->assertTrue($constraint->evaluate($response, '', true));
        $constraint = new ResponseCookieValueSame('foo', 'babar', '/path');
        $this->assertFalse($constraint->evaluate($response, '', true));

        try {
            $constraint->evaluate($response);
        } catch (ExpectationFailedException $e) {
            $this->assertEquals("Failed asserting that the Response has cookie \"foo\" with path \"/path\" with value \"babar\".\n", TestFailure::exceptionToString($e));

            return;
        }

        $this->fail();
    }

    public function testCookieWithNullValueIsComparedAsEmptyString()
    {
        $response = new Response();
        $response->headers->setCookie(Cookie::create('foo', null, 0, '/path'));

        $this->assertTrue((new ResponseCookieValueSame('foo', '', '/path'))->evaluate($response, '', true));
    }
}
