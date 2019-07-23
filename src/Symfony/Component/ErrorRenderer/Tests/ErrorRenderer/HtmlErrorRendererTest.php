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
        $expected = '<!-- Foo (500 Internal Server Error) -->%A<!DOCTYPE html>%A<html lang="en">%A<title>Foo (500 Internal Server Error)</title>%A<!-- Foo (500 Internal Server Error) -->';

        $this->assertStringMatchesFormat($expected, (new HtmlErrorRenderer(true))->render($exception));
    }
}
