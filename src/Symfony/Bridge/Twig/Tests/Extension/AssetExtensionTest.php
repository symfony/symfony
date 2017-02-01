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

use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\Preload\PreloadManager;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class AssetExtensionTest extends \PHPUnit_Framework_TestCase
{
    public function testGetAndPreloadAssetUrl()
    {
        $preloadManager = new PreloadManager();
        $extension = new AssetExtension(new Packages(), $preloadManager);

        $this->assertEquals('/foo.css', $extension->preload('/foo.css', 'style', true));
        $this->assertEquals(array('/foo.css' => array('as' => 'style', 'nopush' => true)), $preloadManager->getResources());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testNoConfiguredPreloadManager()
    {
        $extension = new AssetExtension(new Packages());
        $extension->preload('/foo.css');
    }
}
