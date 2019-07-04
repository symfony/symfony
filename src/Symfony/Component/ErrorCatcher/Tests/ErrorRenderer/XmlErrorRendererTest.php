<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorCatcher\Tests\ErrorRenderer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorCatcher\ErrorRenderer\XmlErrorRenderer;
use Symfony\Component\ErrorCatcher\Exception\FlattenException;

class XmlErrorRendererTest extends TestCase
{
    public function testRender()
    {
        $exception = FlattenException::createFromThrowable(new \RuntimeException('Foo'));
        $expected = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<problem xmlns="urn:ietf:rfc:7807">
  <title>Internal Server Error</title>
  <status>500</status>
  <detail>Foo</detail>
  <exceptions>
    <exception class="RuntimeException" message="Foo">
      <traces>
        <trace>%A
XML;

        $this->assertStringMatchesFormat($expected, (new XmlErrorRenderer())->render($exception));
    }
}
