<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler;

use Symfony\Bridge\Monolog\Processor\ProcessorInterface;
use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Adds a rule to bind "security.actual_token_storage" to ProcessorInterface instances.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class RegisterForAutoconfigurationPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if ($container->has('security.actual_token_storage')) {
            $processorAutoconfiguration = $container->registerForAutoconfiguration(ProcessorInterface::class);
            $processorAutoconfiguration->setBindings($processorAutoconfiguration->getBindings() + array(
                TokenStorageInterface::class => new BoundArgument(new Reference('security.actual_token_storage'), false),
            ));
        }
    }
}
