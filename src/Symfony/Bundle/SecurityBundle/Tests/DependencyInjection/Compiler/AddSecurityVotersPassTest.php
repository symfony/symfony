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
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AddSecurityVotersPassTest extends TestCase
{
    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\LogicException
     */
    public function testNoVoters()
    {
        $container = new ContainerBuilder();
        $container
            ->register('security.access.decision_manager', AccessDecisionManager::class)
            ->addArgument(array())
        ;

        $compilerPass = new AddSecurityVotersPass();
        $compilerPass->process($container);
    }

    public function testThatSecurityVotersAreProcessedInPriorityOrder()
    {
        $container = new ContainerBuilder();
        $container
            ->register('security.access.decision_manager', AccessDecisionManager::class)
            ->addArgument(array())
        ;
        $container
            ->register('no_prio_service', Voter::class)
            ->addTag('security.voter')
        ;
        $container
            ->register('lowest_prio_service', Voter::class)
            ->addTag('security.voter', array('priority' => 100))
        ;
        $container
            ->register('highest_prio_service', Voter::class)
            ->addTag('security.voter', array('priority' => 200))
        ;
        $container
            ->register('zero_prio_service', Voter::class)
            ->addTag('security.voter', array('priority' => 0))
        ;
        $compilerPass = new AddSecurityVotersPass();
        $compilerPass->process($container);

        $argument = $container->getDefinition('security.access.decision_manager')->getArgument(0);
        $refs = $argument->getValues();
        $this->assertEquals(new Reference('highest_prio_service'), $refs[0]);
        $this->assertEquals(new Reference('lowest_prio_service'), $refs[1]);
        $this->assertCount(4, $refs);
    }

    /**
     * @group legacy
     * @expectedDeprecation Using a "security.voter" tag on a class without implementing the "Symfony\Component\Security\Core\Authorization\Voter\VoterInterface" is deprecated as of 3.4 and will throw an exception in 4.0. Implement the interface instead.
     */
    public function testVoterMissingInterface()
    {
        $container = new ContainerBuilder();
        $container
            ->register('security.access.decision_manager', AccessDecisionManager::class)
            ->addArgument(array())
        ;
        $container
            ->register('without_interface', VoterWithoutInterface::class)
            ->addTag('security.voter')
        ;
        $compilerPass = new AddSecurityVotersPass();
        $compilerPass->process($container);

        $argument = $container->getDefinition('security.access.decision_manager')->getArgument(0);
        $refs = $argument->getValues();
        $this->assertEquals(new Reference('without_interface'), $refs[0]);
        $this->assertCount(1, $refs);
    }

    /**
     * @group legacy
     */
    public function testVoterMissingInterfaceAndMethod()
    {
        $exception = LogicException::class;
        $message = 'stdClass should implement the Symfony\Component\Security\Core\Authorization\Voter\VoterInterface interface when used as voter.';

        if (method_exists($this, 'expectException')) {
            $this->expectException($exception);
            $this->expectExceptionMessage($message);
        } else {
            $this->setExpectedException($exception, $message);
        }

        $container = new ContainerBuilder();
        $container
            ->register('security.access.decision_manager', AccessDecisionManager::class)
            ->addArgument(array())
        ;
        $container
            ->register('without_method', 'stdClass')
            ->addTag('security.voter')
        ;
        $compilerPass = new AddSecurityVotersPass();
        $compilerPass->process($container);
    }
}

class VoterWithoutInterface
{
    public function vote()
    {
    }
}
