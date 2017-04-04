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
use Symfony\Bridge\Twig\Extension\PreloadExtension;
use Symfony\Component\Preload\PreloadManager;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PreloadExtensionTest extends TestCase
{
    public function testGetAndPreloadAssetUrl()
    {
        if (!class_exists(PreloadManager::class)) {
            $this->markTestSkipped('Requires Asset 3.3+.');
        }

        $preloadManager = new PreloadManager();
        $extension = new PreloadExtension($preloadManager);

        $this->assertEquals('/foo.css', $extension->preload('/foo.css', 'style', true));
        $this->assertEquals('</foo.css>; rel=preload; as=style; nopush', $preloadManager->buildLinkValue());
    }
}
