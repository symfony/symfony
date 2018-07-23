<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Extension;

use Symfony\Component\DependencyInjection\ContainerBuilder;

interface PrependExtensionInterface
{
    /**
     * Allow an extension to prepend the extension configurations.
     */
    public function prepend(ContainerBuilder $container);
}
