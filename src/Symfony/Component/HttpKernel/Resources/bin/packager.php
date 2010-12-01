<?php

require_once __DIR__.'/../../../HttpFoundation/UniversalClassLoader.php';

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\HttpFoundation\UniversalClassLoader;
use Symfony\Component\HttpKernel\ClassCollectionLoader;

$loader = new UniversalClassLoader();
$loader->registerNamespaces(array('Symfony' => __DIR__.'/../../../../..'));
$loader->register();

if (file_exists(__DIR__.'/../../bootstrap.php')) {
    unlink(__DIR__.'/../../bootstrap.php');
}

ClassCollectionLoader::load(array(
    'Symfony\\Component\\HttpKernel\\Bundle\\Bundle',
    'Symfony\\Component\\HttpKernel\\Bundle\\BundleInterface',
    'Symfony\\Component\\HttpKernel\\Debug\\ErrorHandler',
    'Symfony\\Component\\HttpKernel\\ClassCollectionLoader',

    'Symfony\\Component\\DependencyInjection\\Container',
    'Symfony\\Component\\DependencyInjection\\ContainerAwareInterface',
    'Symfony\\Component\\DependencyInjection\\ContainerInterface',
    'Symfony\\Component\\DependencyInjection\\ParameterBag\\FrozenParameterBag',
    'Symfony\\Component\\DependencyInjection\\ParameterBag\\ParameterBagInterface',
    'Symfony\\Component\\DependencyInjection\\TaggedContainerInterface',

), __DIR__.'/../..', 'bootstrap', false);
