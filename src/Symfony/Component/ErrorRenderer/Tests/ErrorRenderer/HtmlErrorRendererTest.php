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
use Symfony\Component\ErrorRenderer\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorRenderer\Exception\FlattenException;

class HtmlErrorRendererTest extends TestCase
{
    public function testRender()
    {
        $exception = FlattenException::createFromThrowable(new \RuntimeException('Foo'));
        $expected = '<!DOCTYPE html>%A<html>%A<head>%A<title>Internal Server Error</title>%A<h1 class="break-long-words exception-message">Foo</h1>%A<abbr title="RuntimeException">RuntimeException</abbr>%A';

        $this->assertStringMatchesFormat($expected, (new HtmlErrorRenderer())->render($exception));
    }
}
