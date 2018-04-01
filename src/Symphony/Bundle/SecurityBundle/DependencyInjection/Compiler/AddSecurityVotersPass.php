<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\SecurityBundle\DependencyInjection\Compiler;

use Symphony\Component\DependencyInjection\Argument\IteratorArgument;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symphony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symphony\Component\DependencyInjection\Exception\LogicException;
use Symphony\Component\Security\Core\Authorization\Voter\VoterInterface;

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
            throw new LogicException('No security voters found. You need to tag at least one with "security.voter".');
        }

        foreach ($voters as $voter) {
            $definition = $container->getDefinition((string) $voter);
            $class = $container->getParameterBag()->resolveValue($definition->getClass());

            if (!is_a($class, VoterInterface::class, true)) {
                throw new LogicException(sprintf('%s must implement the %s when used as a voter.', $class, VoterInterface::class));
            }
        }

        $adm = $container->getDefinition('security.access.decision_manager');
        $adm->replaceArgument(0, new IteratorArgument($voters));
    }
}
