<?php

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Checks if all configured security voters implements VoterInterface.
 *
 * @author Paulius Jarmalavičius <paulius.jarmalavicius@gmail.com>
 */
class CheckSecurityVotersValidityPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $voters = $this->findAndSortTaggedServices('security.voter', $container);
        foreach ($voters as $voter) {
            $class = $container->getDefinition((string) $voter)->getClass();

            if (!is_a($class, VoterInterface::class, true)) {
                @trigger_error(
                    sprintf(
                        'Using a security.voter tag on a class without implementing the %1$s is deprecated as of 3.4 and will be removed in 4.0. Implement the %1$s instead.',
                        VoterInterface::class
                    ),
                    E_USER_DEPRECATED
                );
            }

            if (!method_exists($class, 'vote')) {
                // in case the vote method is completely missing, to prevent exceptions when voting
                throw new LogicException(
                    sprintf('%s should implement the %s interface when used as voter.', $class, VoterInterface::class)
                );
            }
        }
    }
}
