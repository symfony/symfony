<?php

require_once __DIR__.'/../../../HttpFoundation/UniversalClassLoader.php';

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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

), __DIR__.'/../..', 'bootstrap', false);
