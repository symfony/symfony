<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\SecurityBundle\Tests\Functional\Bundle\FormLoginBundle\DependencyInjection;

use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Extension\Extension;

class FormLoginExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $container
            ->register('localized_form_failure_handler', 'Symphony\Bundle\SecurityBundle\Tests\Functional\Bundle\FormLoginBundle\Security\LocalizedFormFailureHandler')
            ->addArgument(new Reference('router'))
        ;
    }
}
