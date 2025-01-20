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
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseHasHeader;

class ResponseHasHeaderTest extends TestCase
{
    public function testConstraint()
    {
        $constraint = new ResponseHasHeader('Date');
        $this->assertTrue($constraint->evaluate(new Response(), '', true));
        $constraint = new ResponseHasHeader('X-Date');
        $this->assertFalse($constraint->evaluate(new Response(), '', true));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('Failed asserting that the Response has header "X-Date".');

        $constraint->evaluate(new Response());
    }
}
