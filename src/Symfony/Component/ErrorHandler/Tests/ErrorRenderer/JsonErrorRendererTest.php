<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler\Tests\ErrorRenderer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\ErrorRenderer\JsonErrorRenderer;
use Symfony\Component\ErrorHandler\Exception\FlattenException;

class JsonErrorRendererTest extends TestCase
{
    public function testRender()
    {
        $exception = FlattenException::create(new \RuntimeException('Foo'));
        $expected = '{"title":"Internal Server Error","status":500,"detail":"Foo","exceptions":[{"message":"Foo","class":"RuntimeException","trace":';

        $this->assertStringStartsWith($expected, (new JsonErrorRenderer())->render($exception));
    }
}
