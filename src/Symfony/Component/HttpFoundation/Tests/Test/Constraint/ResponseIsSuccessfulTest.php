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
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseIsSuccessful;

class ResponseIsSuccessfulTest extends TestCase
{
    public function testConstraint()
    {
        $constraint = new ResponseIsSuccessful();

        $this->assertTrue($constraint->evaluate(new Response(), '', true));
        $this->assertFalse($constraint->evaluate(new Response('', 404), '', true));

        try {
            $constraint->evaluate(new Response('', 404));
        } catch (ExpectationFailedException $e) {
            $this->assertStringContainsString("Failed asserting that the Response is successful.\nHTTP/1.0 404 Not Found", TestFailure::exceptionToString($e));

            return;
        }

        $this->fail();
    }
}
