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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseIsUnprocessable;

class ResponseIsUnprocessableTest extends TestCase
{
    public function testConstraint()
    {
        $constraint = new ResponseIsUnprocessable();

        $this->assertTrue($constraint->evaluate(new Response('', 422), '', true));
        $this->assertFalse($constraint->evaluate(new Response(), '', true));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessageMatches('/Failed asserting that the Response is unprocessable.\nHTTP\/1.0 200 OK.+Response body/s');

        $constraint->evaluate(new Response('Response body'));
    }

    public function testReducedVerbosity()
    {
        $constraint = new ResponseIsUnprocessable(verbose: false);

        try {
            $constraint->evaluate(new Response('Response body'));
        } catch (ExpectationFailedException $e) {
            $this->assertStringContainsString("Failed asserting that the Response is unprocessable.\nHTTP/1.0 200 OK", $e->getMessage());
            $this->assertStringNotContainsString('Response body', $e->getMessage());

            return;
        }

        $this->fail();
    }
}
