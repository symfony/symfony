<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Tests\Templating;

use Assetic\Asset\AssetCollection;
use Assetic\Asset\AssetInterface;
use Assetic\Asset\StringAsset;
use Assetic\Factory\AssetFactory;
use Symfony\Bundle\AsseticBundle\Templating\AsseticHelper;

class AsseticHelperTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Assetic\\AssetManager')) {
            $this->markTestSkipped('Assetic is not available.');
        }
    }

    /**
     * @dataProvider getDebugAndCount
     */
    public function testUrls($debug, $count, $message)
    {
        $helper = new AsseticHelperForTest(new AssetFactory('/foo', $debug), $debug);
        $urls = $helper->javascripts(array('js/jquery.js', 'js/jquery.plugin.js'));

        $this->assertInstanceOf('Traversable', $urls, '->javascripts() returns an array');
        $this->assertEquals($count, count($urls), $message);
    }

    public function getDebugAndCount()
    {
        return array(
            array(false, 1, '->javascripts() returns one url when not in debug mode'),
            array(true, 2, '->javascripts() returns many urls when in debug mode'),
        );
    }
}

class AsseticHelperForTest extends AsseticHelper
{
    protected function getAssetUrl(AssetInterface $asset, $options = array())
    {
        return $asset->getTargetPath();
    }
}
