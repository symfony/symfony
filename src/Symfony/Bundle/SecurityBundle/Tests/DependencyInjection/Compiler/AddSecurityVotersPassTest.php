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
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AddSecurityVotersPassTest extends TestCase
{
    public function testNoVoters()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\LogicException');
        $this->expectExceptionMessage('No security voters found. You need to tag at least one with "security.voter".');
        $container = new ContainerBuilder();
        $container
            ->register('security.access.decision_manager', AccessDecisionManager::class)
            ->addArgument([])
        ;

        $compilerPass = new AddSecurityVotersPass();
        $compilerPass->process($container);
    }

    public function testThatSecurityVotersAreProcessedInPriorityOrder()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);

        $container
            ->register('security.access.decision_manager', AccessDecisionManager::class)
            ->addArgument([])
        ;
        $container
            ->register('no_prio_service', Voter::class)
            ->addTag('security.voter')
        ;
        $container
            ->register('lowest_prio_service', Voter::class)
            ->addTag('security.voter', ['priority' => 100])
        ;
        $container
            ->register('highest_prio_service', Voter::class)
            ->addTag('security.voter', ['priority' => 200])
        ;
        $container
            ->register('zero_prio_service', Voter::class)
            ->addTag('security.voter', ['priority' => 0])
        ;
        $compilerPass = new AddSecurityVotersPass();
        $compilerPass->process($container);

        $argument = $container->getDefinition('security.access.decision_manager')->getArgument(0);
        $refs = $argument->getValues();
        $this->assertEquals(new Reference('highest_prio_service'), $refs[0]);
        $this->assertEquals(new Reference('lowest_prio_service'), $refs[1]);
        $this->assertCount(4, $refs);
    }

    public function testThatVotersAreTraceableInDebugMode(): void
    {
        $container = new ContainerBuilder();

        $voterDef1 = new Definition(Voter::class);
        $voterDef1->addTag('security.voter');
        $container->setDefinition('voter1', $voterDef1);

        $voterDef2 = new Definition(Voter::class);
        $voterDef2->addTag('security.voter');
        $container->setDefinition('voter2', $voterDef2);

        $container
            ->register('security.access.decision_manager', AccessDecisionManager::class)
            ->addArgument([$voterDef1, $voterDef2]);
        $container->setParameter('kernel.debug', true);

        $compilerPass = new AddSecurityVotersPass();
        $compilerPass->process($container);

        $def1 = $container->getDefinition('debug.security.voter.voter1');
        $this->assertNull($def1->getDecoratedService(), 'voter1: should not be decorated');
        $this->assertEquals(new Reference('voter1'), $def1->getArgument(0), 'voter1: wrong argument');

        $def2 = $container->getDefinition('debug.security.voter.voter2');
        $this->assertNull($def2->getDecoratedService(), 'voter2: should not be decorated');
        $this->assertEquals(new Reference('voter2'), $def2->getArgument(0), 'voter2: wrong argument');

        $voters = $container->findTaggedServiceIds('security.voter');
        $this->assertCount(2, $voters, 'Incorrect count of voters');
    }

    public function testThatVotersAreNotTraceableWithoutDebugMode(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);

        $voterDef1 = new Definition(Voter::class);
        $voterDef1->addTag('security.voter');
        $container->setDefinition('voter1', $voterDef1);

        $voterDef2 = new Definition(Voter::class);
        $voterDef2->addTag('security.voter');
        $container->setDefinition('voter2', $voterDef2);

        $container
            ->register('security.access.decision_manager', AccessDecisionManager::class)
            ->addArgument([$voterDef1, $voterDef2]);

        $compilerPass = new AddSecurityVotersPass();
        $compilerPass->process($container);

        $this->assertFalse($container->has('debug.security.voter.voter1'), 'voter1 should not be traced');
        $this->assertFalse($container->has('debug.security.voter.voter2'), 'voter2 should not be traced');
    }

    public function testVoterMissingInterface()
    {
        $exception = LogicException::class;
        $message = 'stdClass must implement the Symfony\Component\Security\Core\Authorization\Voter\VoterInterface when used as a voter.';

        $this->expectException($exception);
        $this->expectExceptionMessage($message);

        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container
            ->register('security.access.decision_manager', AccessDecisionManager::class)
            ->addArgument([])
        ;
        $container
            ->register('without_interface', 'stdClass')
            ->addTag('security.voter')
        ;
        $compilerPass = new AddSecurityVotersPass();
        $compilerPass->process($container);
    }
}
