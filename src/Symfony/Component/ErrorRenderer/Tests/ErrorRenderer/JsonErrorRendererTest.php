<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorRenderer\Tests\ErrorRenderer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorRenderer\ErrorRenderer\JsonErrorRenderer;
use Symfony\Component\ErrorRenderer\Exception\FlattenException;

class JsonErrorRendererTest extends TestCase
{
    public function testRender()
    {
        $exception = FlattenException::createFromThrowable(new \RuntimeException('Foo'));
        $expected = <<<JSON
{
    "title": "Internal Server Error",
    "status": 500,
    "detail": "Foo",
    "exceptions": [
        {
            "message": "Foo",
            "class": "RuntimeException",
            "trace":
JSON;

        $this->assertStringStartsWith($expected, (new JsonErrorRenderer())->render($exception));
    }
}
