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
use Symfony\Component\ErrorRenderer\ErrorRenderer\XmlErrorRenderer;
use Symfony\Component\ErrorRenderer\Exception\FlattenException;

class XmlErrorRendererTest extends TestCase
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
        $expectedDebug = <<<XML
<?xml version="1.0" encoding="UTF-8" ?>
<problem xmlns="urn:ietf:rfc:7807">
    <title>Internal Server Error</title>
    <status>500</status>
    <detail>Foo</detail>
    <exceptions><exception class="RuntimeException" message="Foo"><traces><trace>%A</trace></traces></exception></exceptions>
</problem>
XML;

        $expectedNonDebug = <<<XML
<?xml version="1.0" encoding="UTF-8" ?>
<problem xmlns="urn:ietf:rfc:7807">
    <title>Internal Server Error</title>
    <status>500</status>
    <detail>Whoops, looks like something went wrong.</detail>
    
</problem>
XML;

        yield '->render() returns the XML content WITH stack traces in debug mode' => [
            FlattenException::createFromThrowable(new \RuntimeException('Foo')),
            new XmlErrorRenderer(true),
            $expectedDebug,
        ];

        yield '->render() returns the XML content WITHOUT stack traces in non-debug mode' => [
            FlattenException::createFromThrowable(new \RuntimeException('Foo')),
            new XmlErrorRenderer(false),
            $expectedNonDebug,
        ];

        yield '->render() returns the XML content WITHOUT stack traces in debug mode FORCING non-debug via X-Debug header' => [
            FlattenException::createFromThrowable(new \RuntimeException('Foo'), null, ['X-Debug' => false]),
            new XmlErrorRenderer(true),
            $expectedNonDebug,
        ];

        yield '->render() returns the XML content WITHOUT stack traces in non-debug mode EVEN FORCING debug via X-Debug header' => [
            FlattenException::createFromThrowable(new \RuntimeException('Foo'), null, ['X-Debug' => true]),
            new XmlErrorRenderer(false),
            $expectedNonDebug,
        ];
    }
}
