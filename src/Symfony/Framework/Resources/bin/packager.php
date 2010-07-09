<?php

require_once __DIR__.'/../../UniversalClassLoader.php';

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Framework\UniversalClassLoader;
use Symfony\Framework\ClassCollectionLoader;

$loader = new UniversalClassLoader();
$loader->registerNamespaces(array('Symfony' => __DIR__.'/../../../..'));
$loader->register();

if (file_exists(__DIR__.'/../../bootstrap.php')) {
    unlink(__DIR__.'/../../bootstrap.php');
}

ClassCollectionLoader::load(array(
    'Symfony\\Framework\\Bundle\\Bundle',
    'Symfony\\Framework\\Bundle\\BundleInterface',
    'Symfony\\Framework\\KernelBundle',
    'Symfony\\Framework\\DependencyInjection\\KernelExtension',
    'Symfony\\Framework\\Debug\\ErrorHandler',
    'Symfony\\Framework\\ClassCollectionLoader',
    'Symfony\\Framework\\EventDispatcher',
), __DIR__.'/../..', 'bootstrap', false);
