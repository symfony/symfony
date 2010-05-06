<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Templating\Helper;

use Symfony\Components\Templating\Helper\AssetsHelper;
use Symfony\Components\Templating\Helper\JavascriptsHelper;
use Symfony\Components\Templating\Loader\FilesystemLoader;

class JavascriptsHelperTest extends \PHPUnit_Framework_TestCase
{
    public function testAdd()
    {
        $assetHelper = new AssetsHelper();
        $helper = new JavascriptsHelper($assetHelper);
        $helper->add('foo');
        $this->assertEquals(array('/foo' => array()), $helper->get(), '->add() adds a JavaScript');
        $helper->add('/foo');
        $this->assertEquals(array('/foo' => array()), $helper->get(), '->add() does not add the same JavaScript twice');
        $helper = new JavascriptsHelper($assetHelper);
        $assetHelper->setBaseURLs('http://assets.example.com/');
        $helper->add('foo');
        $this->assertEquals(array('http://assets.example.com/foo' => array()), $helper->get(), '->add() converts the JavaScript to a public path');
    }

    public function testMagicToString()
    {
        $assetHelper = new AssetsHelper();
        $assetHelper->setBaseURLs('');
        $helper = new JavascriptsHelper($assetHelper);
        $helper->add('foo', array('class' => 'ba>'));
        $this->assertEquals('<script type="text/javascript" src="/foo" class="ba&gt;"></script>'."\n", $helper->__toString(), '->__toString() converts the JavaScript configuration to HTML');
    }
}
