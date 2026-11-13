<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\Resources;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class ProfilerOverlayTest extends TestCase
{
    public function testOverlayRemainsOutsideTheReplaceableToolbar()
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2).'/Resources/views', 'WebProfiler');
        $twig = new Environment(new ChainLoader([
            new ArrayLoader(['@WebProfiler/Profiler/toolbar.html.twig' => '<a href="{{ profiler_url }}">Profiler</a>']),
            $loader,
        ]), ['strict_variables' => true]);
        $twig->addFunction(new TwigFunction('url', static fn (string $route, array $parameters = []) => match ($route) {
            '_profiler_home' => 'https://example.com/app/_debug/profile/',
            '_profiler' => 'https://example.com/app/_debug/profile/'.$parameters['token'],
            '_wdt' => 'https://example.com/app/_debug/toolbar/'.$parameters['token'],
            '_wdt_stylesheet' => 'https://example.com/app/_debug/toolbar.css',
        }));

        $html = $twig->render('@WebProfiler/Profiler/toolbar_js.html.twig', [
            'token' => 'abc123',
            'csp_script_nonce' => null,
            'csp_style_nonce' => null,
        ]);

        $document = new \DOMDocument();
        $document->loadHTML($html, \LIBXML_NOERROR | \LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $overlay = $xpath->query('//dialog[contains(concat(" ", normalize-space(@class), " "), " sf-profiler-overlay ")]');

        $this->assertCount(1, $overlay);
        $this->assertSame('Symfony Profiler', $overlay->item(0)->getAttribute('aria-label'));
        $this->assertCount(0, $xpath->query('ancestor::*[@id="sfwdtabc123"]', $overlay->item(0)));
        $this->assertCount(1, $xpath->query('.//button[@type="button" and normalize-space(.)="Close"]', $overlay->item(0)));

        $link = $xpath->query('.//a[@target="_blank"]', $overlay->item(0));
        $this->assertCount(1, $link);
        $this->assertSame('https://example.com/app/_debug/profile/abc123', $link->item(0)->getAttribute('href'));
        $this->assertContains('noopener', explode(' ', $link->item(0)->getAttribute('rel')));
    }
}
