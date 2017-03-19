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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Adds all configured security voters to the access decision manager.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class AddSecurityVotersPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('security.access.decision_manager')) {
            return;
        }

        $voters = $this->findAndSortTaggedServices('security.voter', $container);
        if (!$voters) {
            throw new LogicException('No security voters found. You need to tag at least one with "security.voter"');
        }

        $adm = $container->getDefinition($container->hasDefinition('debug.security.access.decision_manager') ? 'debug.security.access.decision_manager' : 'security.access.decision_manager');
        $adm->addMethodCall('setVoters', array($voters));
    }
}
