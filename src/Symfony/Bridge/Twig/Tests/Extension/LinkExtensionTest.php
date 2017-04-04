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
use Symfony\Bridge\Twig\Extension\LinkExtension;
use Symfony\Component\Link\LinkManager;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class LinkExtensionTest extends TestCase
{
    public function testLink()
    {
        $linkManager = new LinkManager();
        $extension = new LinkExtension($linkManager);

        $this->assertEquals('/foo.css', $extension->link('/foo.css', 'preload', array('as' => 'style', 'nopush' => true)));
        $this->assertEquals('</foo.css>; rel=preload; as=style; nopush', $linkManager->buildValues());
    }

    public function testPreload()
    {
        $linkManager = new LinkManager();
        $extension = new LinkExtension($linkManager);

        $this->assertEquals('/foo.css', $extension->preload('/foo.css', array('as' => 'style', 'crossorigin' => true)));
        $this->assertEquals('</foo.css>; rel=preload; as=style; crossorigin', $linkManager->buildValues());
    }

    public function testDnsPrefetch()
    {
        $linkManager = new LinkManager();
        $extension = new LinkExtension($linkManager);

        $this->assertEquals('/foo.css', $extension->dnsPrefetch('/foo.css', array('as' => 'style', 'crossorigin' => true)));
        $this->assertEquals('</foo.css>; rel=dns-prefetch; as=style; crossorigin', $linkManager->buildValues());
    }

    public function testPreconnect()
    {
        $linkManager = new LinkManager();
        $extension = new LinkExtension($linkManager);

        $this->assertEquals('/foo.css', $extension->preconnect('/foo.css', array('as' => 'style', 'crossorigin' => true)));
        $this->assertEquals('</foo.css>; rel=preconnect; as=style; crossorigin', $linkManager->buildValues());
    }

    public function testPrefetch()
    {
        $linkManager = new LinkManager();
        $extension = new LinkExtension($linkManager);

        $this->assertEquals('/foo.css', $extension->prefetch('/foo.css', array('as' => 'style', 'crossorigin' => true)));
        $this->assertEquals('</foo.css>; rel=prefetch; as=style; crossorigin', $linkManager->buildValues());
    }

    public function testPrerender()
    {
        $linkManager = new LinkManager();
        $extension = new LinkExtension($linkManager);

        $this->assertEquals('/foo.css', $extension->prerender('/foo.css', array('as' => 'style', 'crossorigin' => true)));
        $this->assertEquals('</foo.css>; rel=prerender; as=style; crossorigin', $linkManager->buildValues());
    }
}
