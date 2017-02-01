<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\Preload;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PreloadManagerTest extends \PHPUnit_Framework_TestCase
{
    public function testManageResources()
    {
        $manager = new PreloadManager();
        $this->assertInstanceOf(PreloadManagerInterface::class, $manager);

        $manager->addResource('/foo/bar.js', 'script', false);
        $manager->addResource('/foo/baz.css');
        $manager->addResource('/foo/bat.png', 'image', true);

        $this->assertEquals('</foo/bar.js>; rel=preload; as=script,</foo/baz.css>; rel=preload,</foo/bat.png>; rel=preload; as=image; nopush', $manager->buildLinkValue());
    }
}
