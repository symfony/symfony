<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;

interface PreProcessExtensionInterface
{
    /**
     * Allow an extension to pre-process the extension configurations
     *
     * @param ContainerBuilder $container
     */
    function preProcess(ContainerBuilder $container);
}
