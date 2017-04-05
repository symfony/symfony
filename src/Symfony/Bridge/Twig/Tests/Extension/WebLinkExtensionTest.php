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
use Symfony\Bridge\Twig\Extension\WebLinkExtension;
use Symfony\Component\WebLink\WebLinkManager;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class WebLinkExtensionTest extends TestCase
{
    public function testLink()
    {
        $linkManager = new WebLinkManager();
        $extension = new WebLinkExtension($linkManager);

        $this->assertEquals('/foo.css', $extension->link('/foo.css', 'preload', array('as' => 'style', 'nopush' => true)));
        $this->assertEquals('</foo.css>; rel=preload; as=style; nopush', $linkManager->buildHeaderValue());
    }

    public function testPreload()
    {
        $linkManager = new WebLinkManager();
        $extension = new WebLinkExtension($linkManager);

        $this->assertEquals('/foo.css', $extension->preload('/foo.css', array('as' => 'style', 'crossorigin' => true)));
        $this->assertEquals('</foo.css>; rel=preload; as=style; crossorigin', $linkManager->buildHeaderValue());
    }

    public function testDnsPrefetch()
    {
        $linkManager = new WebLinkManager();
        $extension = new WebLinkExtension($linkManager);

        $this->assertEquals('/foo.css', $extension->dnsPrefetch('/foo.css', array('as' => 'style', 'crossorigin' => true)));
        $this->assertEquals('</foo.css>; rel=dns-prefetch; as=style; crossorigin', $linkManager->buildHeaderValue());
    }

    public function testPreconnect()
    {
        $linkManager = new WebLinkManager();
        $extension = new WebLinkExtension($linkManager);

        $this->assertEquals('/foo.css', $extension->preconnect('/foo.css', array('as' => 'style', 'crossorigin' => true)));
        $this->assertEquals('</foo.css>; rel=preconnect; as=style; crossorigin', $linkManager->buildHeaderValue());
    }

    public function testPrefetch()
    {
        $linkManager = new WebLinkManager();
        $extension = new WebLinkExtension($linkManager);

        $this->assertEquals('/foo.css', $extension->prefetch('/foo.css', array('as' => 'style', 'crossorigin' => true)));
        $this->assertEquals('</foo.css>; rel=prefetch; as=style; crossorigin', $linkManager->buildHeaderValue());
    }

    public function testPrerender()
    {
        $linkManager = new WebLinkManager();
        $extension = new WebLinkExtension($linkManager);

        $this->assertEquals('/foo.css', $extension->prerender('/foo.css', array('as' => 'style', 'crossorigin' => true)));
        $this->assertEquals('</foo.css>; rel=prerender; as=style; crossorigin', $linkManager->buildHeaderValue());
    }

    public function testGetName()
    {
        $this->assertEquals('web_link', (new WebLinkExtension(new WebLinkManager()))->getName());
    }
}
