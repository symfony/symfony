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
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseIsRedirected;

class ResponseIsRedirectedTest extends TestCase
{
    public function testConstraint()
    {
        $constraint = new ResponseIsRedirected();

        $this->assertTrue($constraint->evaluate(new Response('', 301), '', true));
        $this->assertFalse($constraint->evaluate(new Response(), '', true));

        try {
            $constraint->evaluate(new Response('Body content'));
        } catch (ExpectationFailedException $e) {
            $exceptionMessage = TestFailure::exceptionToString($e);
            $this->assertStringContainsString("Failed asserting that the Response is redirected.\nHTTP/1.0 200 OK", $exceptionMessage);
            $this->assertStringContainsString('Body content', $exceptionMessage);

            return;
        }

        $this->fail();
    }

    public function testReducedVerbosity()
    {
        $constraint = new ResponseIsRedirected(verbose: false);
        try {
            $constraint->evaluate(new Response('Body content'));
        } catch (ExpectationFailedException $e) {
            $exceptionMessage = TestFailure::exceptionToString($e);
            $this->assertStringContainsString("Failed asserting that the Response is redirected.\nHTTP/1.0 200 OK", $exceptionMessage);
            $this->assertStringNotContainsString('Body content', $exceptionMessage);

            return;
        }

        $this->fail();
    }
}
