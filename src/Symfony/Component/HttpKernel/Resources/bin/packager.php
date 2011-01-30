<?php

require_once __DIR__.'/../../../ClassLoader/UniversalClassLoader.php';

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\ClassLoader\UniversalClassLoader;
use Symfony\Component\ClassLoader\ClassCollectionLoader;

$loader = new UniversalClassLoader();
$loader->registerNamespaces(array('Symfony' => __DIR__.'/../../../../..'));
$loader->register();

if (file_exists(__DIR__.'/../../bootstrap.php')) {
    unlink(__DIR__.'/../../bootstrap.php');
}

ClassCollectionLoader::load(array(
    'Symfony\\Component\\DependencyInjection\\ContainerInterface',
    'Symfony\\Component\\DependencyInjection\\Container',
    'Symfony\\Component\\DependencyInjection\\ContainerAwareInterface',
    'Symfony\\Component\\DependencyInjection\\ContainerAware',

    'Symfony\\Component\\HttpKernel\\Bundle\\BundleInterface',
    'Symfony\\Component\\HttpKernel\\Bundle\\Bundle',
    'Symfony\\Component\\HttpKernel\\Debug\\ErrorHandler',
    'Symfony\\Component\\HttpKernel\\HttpKernelInterface',
    'Symfony\\Component\\HttpKernel\\HttpKernel',
    'Symfony\\Component\\HttpKernel\\KernelInterface',
    'Symfony\\Component\\HttpKernel\\Kernel',

    'Symfony\\Component\\HttpFoundation\\ParameterBag',
    'Symfony\\Component\\HttpFoundation\\FileBag',
    'Symfony\\Component\\HttpFoundation\\ServerBag',
    'Symfony\\Component\\HttpFoundation\\HeaderBag',
    'Symfony\\Component\\HttpFoundation\\Request',
    'Symfony\\Component\\HttpFoundation\\ApacheRequest',

    'Symfony\\Component\\ClassLoader\\ClassCollectionLoader',
    'Symfony\\Component\\ClassLoader\\UniversalClassLoader',
    'Symfony\\Component\\ClassLoader\\MapFileClassLoader',
), __DIR__.'/../..', 'bootstrap', false);

if (file_exists(__DIR__.'/../../bootstrap_cache.php')) {
    unlink(__DIR__.'/../../bootstrap_cache.php');
}

ClassCollectionLoader::load(array(
    'Symfony\\Component\\HttpKernel\\KernelInterface',
    'Symfony\\Component\\HttpKernel\\Kernel',
    'Symfony\\Component\\HttpKernel\\HttpKernelInterface',
    'Symfony\\Component\\HttpKernel\\HttpCache\\HttpCache',
    'Symfony\\Component\\HttpKernel\\HttpCache\\StoreInterface',
    'Symfony\\Component\\HttpKernel\\HttpCache\\Store',
    'Symfony\\Component\\HttpKernel\\HttpCache\\Esi',

    'Symfony\\Component\\HttpFoundation\\ParameterBag',
    'Symfony\\Component\\HttpFoundation\\FileBag',
    'Symfony\\Component\\HttpFoundation\\ServerBag',
    'Symfony\\Component\\HttpFoundation\\HeaderBag',
    'Symfony\\Component\\HttpFoundation\\Request',
    'Symfony\\Component\\HttpFoundation\\ApacheRequest',
    'Symfony\\Component\\HttpFoundation\\ResponseHeaderBag',
    'Symfony\\Component\\HttpFoundation\\Response',

    'Symfony\\Component\\ClassLoader\\UniversalClassLoader',
), __DIR__.'/../..', 'bootstrap_cache', false);
