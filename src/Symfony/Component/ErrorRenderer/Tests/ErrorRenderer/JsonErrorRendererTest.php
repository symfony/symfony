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
use Symfony\Component\ErrorRenderer\ErrorRenderer\ErrorRendererInterface;
use Symfony\Component\ErrorRenderer\ErrorRenderer\JsonErrorRenderer;
use Symfony\Component\ErrorRenderer\Exception\FlattenException;

class JsonErrorRendererTest extends TestCase
{
    /**
     * @dataProvider getRenderData
     */
    public function testRender(FlattenException $exception, ErrorRendererInterface $errorRenderer, string $expected)
    {
        $this->assertStringMatchesFormat($expected, $errorRenderer->render($exception));
    }

    public function getRenderData(): iterable
    {
        $expectedDebug = <<<JSON
{
    "title": "Internal Server Error",
    "status": 500,
    "detail": "Foo",
    "exceptions": [
        {
            "message": "Foo",
            "class": "RuntimeException",
            "trace": [
%A
JSON;

        $expectedNonDebug = <<<JSON
{
    "title": "Internal Server Error",
    "status": 500,
    "detail": "Whoops, looks like something went wrong."
}
JSON;

        yield '->render() returns the JSON content WITH stack traces in debug mode' => [
            FlattenException::createFromThrowable(new \RuntimeException('Foo')),
            new JsonErrorRenderer(true),
            $expectedDebug,
        ];

        yield '->render() returns the JSON content WITHOUT stack traces in non-debug mode' => [
            FlattenException::createFromThrowable(new \RuntimeException('Foo')),
            new JsonErrorRenderer(false),
            $expectedNonDebug,
        ];

        yield '->render() returns the JSON content WITHOUT stack traces in debug mode FORCING non-debug via X-Debug header' => [
            FlattenException::createFromThrowable(new \RuntimeException('Foo'), null, ['X-Debug' => false]),
            new JsonErrorRenderer(true),
            $expectedNonDebug,
        ];

        yield '->render() returns the JSON content WITHOUT stack traces in non-debug mode EVEN FORCING debug via X-Debug header' => [
            FlattenException::createFromThrowable(new \RuntimeException('Foo'), null, ['X-Debug' => true]),
            new JsonErrorRenderer(false),
            $expectedNonDebug,
        ];
    }
}
