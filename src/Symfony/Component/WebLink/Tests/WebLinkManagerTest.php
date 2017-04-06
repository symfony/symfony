<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\WebLink\Tests;

use Fig\Link\GenericLinkProvider;
use Fig\Link\Link;
use PHPUnit\Framework\TestCase;
use Symfony\Component\WebLink\WebLinkManager;
use Symfony\Component\WebLink\WebLinkManagerInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class WebLinkManagerTest extends TestCase
{
    /**
     * @var WebLinkManager
     */
    private $manager;

    protected function setUp()
    {
        $this->manager = new WebLinkManager(new GenericLinkProvider());
    }

    public function testAdd()
    {
        $this->assertInstanceOf(WebLinkManagerInterface::class, $this->manager);

        $this->manager->add($link1 = new Link());
        $this->manager->add($link2 = new Link());

        $this->assertSame(array($link1, $link2), array_values($this->manager->getLinkProvider()->getLinks()));
    }

    public function testClear()
    {
        $this->manager->add($link1 = new Link());
        $this->manager->add($link2 = new Link());

        $this->manager->clear();

        $this->assertEmpty($this->manager->getLinkProvider()->getLinks());
    }
}
