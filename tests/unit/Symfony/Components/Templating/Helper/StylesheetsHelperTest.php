<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

use Symfony\Components\Templating\Helper\AssetsHelper;
use Symfony\Components\Templating\Helper\StylesheetsHelper;
use Symfony\Components\Templating\Helper\HelperSet;
use Symfony\Components\Templating\Engine;
use Symfony\Components\Templating\Loader\FilesystemLoader;

$t = new LimeTest(4);

$helperSet = new HelperSet(array(
  new AssetsHelper(),
));

// ->add()
$t->diag('->add()');
$helper = new StylesheetsHelper();
$helperSet->set($helper);
$helper->add('foo');
$t->is($helper->get(), array('/foo' => array()), '->add() adds a stylesheet');
$helper->add('/foo');
$t->is($helper->get(), array('/foo' => array()), '->add() does not add the same stylesheet twice');
$helper = new StylesheetsHelper();
$helperSet->set($helper);
$helperSet->get('assets')->setBaseURLs('http://assets.example.com/');
$helper->add('foo');
$t->is($helper->get(), array('http://assets.example.com/foo' => array()), '->add() converts the stylesheet to a public path');

// ->__toString()
$t->diag('->__toString()');
$helper = new StylesheetsHelper();
$helperSet->set($helper);
$helperSet->get('assets')->setBaseURLs('');
$helperSet->setEngine($engine = new Engine(new FilesystemLoader('/')));
$helper->add('foo', array('media' => 'ba>'));
$t->is($helper->__toString(), '<link href="/foo" rel="stylesheet" type="text/css" media="ba&gt;" />', '->__toString() converts the stylesheet configuration to HTML');
