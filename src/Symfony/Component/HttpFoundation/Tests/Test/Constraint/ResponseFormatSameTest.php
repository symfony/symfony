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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseFormatSame;

/**
 * @author Kévin Dunglas <kevin@dunglas.fr>
 */
class ResponseFormatSameTest extends TestCase
{
    public function testConstraint()
    {
        $request = new Request();
        $request->setFormat('custom', ['application/vnd.myformat']);

        $constraint = new ResponseFormatSame($request, 'custom');
        $this->assertTrue($constraint->evaluate(new Response('', 200, ['Content-Type' => 'application/vnd.myformat']), '', true));
        $this->assertFalse($constraint->evaluate(new Response(), '', true));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage("Failed asserting that the Response format is custom.\nHTTP/1.0 200 OK");

        $constraint->evaluate(new Response('', 200, ['Content-Type' => 'application/ld+json']));
    }

    public function testNullFormat()
    {
        $constraint = new ResponseFormatSame(new Request(), null);
        $this->assertTrue($constraint->evaluate(new Response(), '', true));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage("Failed asserting that the Response format is null.\nHTTP/1.0 200 OK");

        $constraint->evaluate(new Response('', 200, ['Content-Type' => 'application/ld+json']));
    }
}
