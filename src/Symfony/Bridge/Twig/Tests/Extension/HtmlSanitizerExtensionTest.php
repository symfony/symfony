<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\HtmlSanitizerExtension;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

class HtmlSanitizerExtensionTest extends TestCase
{
    public function testSanitizeHtml()
    {
        $loader = new ArrayLoader([
            'foo' => '{{ "foobar"|sanitize_html }}',
            'bar' => '{{ "foobar"|sanitize_html("bar") }}',
        ]);

        $twig = new Environment($loader, ['debug' => true, 'cache' => false, 'autoescape' => 'html', 'optimizations' => 0]);

        $fooSanitizer = $this->createMock(HtmlSanitizerInterface::class);
        $fooSanitizer->expects($this->once())
            ->method('sanitize')
            ->with('foobar')
            ->willReturn('foo');

        $barSanitizer = $this->createMock(HtmlSanitizerInterface::class);
        $barSanitizer->expects($this->once())
            ->method('sanitize')
            ->with('foobar')
            ->willReturn('bar');

        $extension = new HtmlSanitizerExtension(new ServiceLocator([
            'foo' => static fn () => $fooSanitizer,
            'bar' => static fn () => $barSanitizer,
        ]), 'foo');
        $twig->addExtension(new AttributeExtension(HtmlSanitizerExtension::class));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            HtmlSanitizerExtension::class => static fn () => $extension,
        ]));

        $this->assertSame('foo', $twig->render('foo'));
        $this->assertSame('bar', $twig->render('bar'));
    }
}
