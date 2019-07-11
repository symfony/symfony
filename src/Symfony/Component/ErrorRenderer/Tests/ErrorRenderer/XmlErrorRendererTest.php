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
use Symfony\Component\ErrorRenderer\ErrorRenderer\XmlErrorRenderer;
use Symfony\Component\ErrorRenderer\Exception\FlattenException;

class XmlErrorRendererTest extends TestCase
{
    public function testRender()
    {
        $exception = FlattenException::createFromThrowable(new \RuntimeException('Foo'));
        $expected = '<?xml version="1.0" encoding="UTF-8" ?>%A<problem xmlns="urn:ietf:rfc:7807">%A<title>Internal Server Error</title>%A<status>500</status>%A<detail>Foo</detail>%A';

        $this->assertStringMatchesFormat($expected, (new XmlErrorRenderer())->render($exception));
    }
}
