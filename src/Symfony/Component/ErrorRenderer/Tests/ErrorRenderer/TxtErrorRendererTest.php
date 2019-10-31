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
use Symfony\Component\ErrorRenderer\ErrorRenderer\TxtErrorRenderer;
use Symfony\Component\ErrorRenderer\Exception\FlattenException;

class TxtErrorRendererTest extends TestCase
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
        $expectedDebug = <<<TXT
[title] Internal Server Error
[status] 500
[detail] Foo
[1] RuntimeException: Foo
in %A
TXT;

        $expectedNonDebug = <<<TXT
[title] Internal Server Error
[status] 500
[detail] Whoops, looks like something went wrong.
TXT;

        yield '->render() returns the TXT content WITH stack traces in debug mode' => [
            FlattenException::createFromThrowable(new \RuntimeException('Foo')),
            new TxtErrorRenderer(true),
            $expectedDebug,
        ];

        yield '->render() returns the TXT content WITHOUT stack traces in non-debug mode' => [
            FlattenException::createFromThrowable(new \RuntimeException('Foo')),
            new TxtErrorRenderer(false),
            $expectedNonDebug,
        ];

        yield '->render() returns the TXT content WITHOUT stack traces in debug mode FORCING non-debug via X-Debug header' => [
            FlattenException::createFromThrowable(new \RuntimeException('Foo'), null, ['X-Debug' => false]),
            new TxtErrorRenderer(true),
            $expectedNonDebug,
        ];

        yield '->render() returns the TXT content WITHOUT stack traces in non-debug mode EVEN FORCING debug via X-Debug header' => [
            FlattenException::createFromThrowable(new \RuntimeException('Foo'), null, ['X-Debug' => true]),
            new TxtErrorRenderer(false),
            $expectedNonDebug,
        ];
    }
}
