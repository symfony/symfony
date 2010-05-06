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
use Symfony\Components\Templating\Helper\StylesheetsHelper;
use Symfony\Components\Templating\Loader\FilesystemLoader;

class StylesheetsHelperTest extends \PHPUnit_Framework_TestCase
{
    public function testAdd()
    {
        $assetHelper = new AssetsHelper();
        $helper = new StylesheetsHelper($assetHelper);
        $helper->add('foo');
        $this->assertEquals(array('/foo' => array()), $helper->get(), '->add() adds a stylesheet');
        $helper->add('/foo');
        $this->assertEquals(array('/foo' => array()), $helper->get(), '->add() does not add the same stylesheet twice');
        $helper = new StylesheetsHelper($assetHelper);
        $assetHelper->setBaseURLs('http://assets.example.com/');
        $helper->add('foo');
        $this->assertEquals(array('http://assets.example.com/foo' => array()), $helper->get(), '->add() converts the stylesheet to a public path');
    }

    public function testMagicToString()
    {
        $assetHelper = new AssetsHelper();
        $assetHelper->setBaseURLs('');
        $helper = new StylesheetsHelper($assetHelper);
        $helper->add('foo', array('media' => 'ba>'));
        $this->assertEquals('<link href="/foo" rel="stylesheet" type="text/css" media="ba&gt;" />'."\n", $helper->__toString(), '->__toString() converts the stylesheet configuration to HTML');
    }
}
