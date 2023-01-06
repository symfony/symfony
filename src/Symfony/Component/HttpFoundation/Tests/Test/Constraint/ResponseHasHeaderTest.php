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
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseHasHeader;

class ResponseHasHeaderTest extends TestCase
{
    public function testConstraint()
    {
        $constraint = new ResponseHasHeader('Date');
        $this->assertTrue($constraint->evaluate(new Response(), '', true));
        $constraint = new ResponseHasHeader('X-Date');
        $this->assertFalse($constraint->evaluate(new Response(), '', true));

        try {
            $constraint->evaluate(new Response());
        } catch (ExpectationFailedException $e) {
            $this->assertEquals("Failed asserting that the Response has header \"X-Date\".\n", TestFailure::exceptionToString($e));

            return;
        }

        $this->fail();
    }
}
