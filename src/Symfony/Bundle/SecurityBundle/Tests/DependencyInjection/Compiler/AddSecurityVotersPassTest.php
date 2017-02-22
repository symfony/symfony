<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\AddSecurityVotersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class AddSecurityVotersPassTest extends TestCase
{
    public function testThatSecurityVotersAreProcessedInPriorityOrder()
    {
        $container = new ContainerBuilder();
        $container
            ->register('security.access.decision_manager', 'Symfony\Component\Security\Core\Authorization\AccessDecisionManager')
            ->addArgument(array())
        ;
        $container
            ->register('no_prio_service')
            ->addTag('security.voter')
        ;
        $container
            ->register('lowest_prio_service')
            ->addTag('security.voter', array('priority' => 100))
        ;
        $container
            ->register('highest_prio_service')
            ->addTag('security.voter', array('priority' => 200))
        ;
        $container
            ->register('zero_prio_service')
            ->addTag('security.voter', array('priority' => 0))
        ;
        $compilerPass = new AddSecurityVotersPass();
        $compilerPass->process($container);

        $calls = $container->getDefinition('security.access.decision_manager')->getMethodCalls();

        $this->assertEquals(
            array(
                new Reference('highest_prio_service'),
                new Reference('lowest_prio_service'),
                new Reference('no_prio_service'),
                new Reference('zero_prio_service'),
            ),
            $calls[0][1][0]
        );
    }
}
