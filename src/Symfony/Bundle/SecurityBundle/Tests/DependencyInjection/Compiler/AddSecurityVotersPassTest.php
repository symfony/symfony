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
use Symfony\Bundle\SecurityBundle\Attribute\AsVoter;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\AddSecurityVotersPass;
use Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Fixtures\Voter\AsVoterDefaultPriority;
use Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Fixtures\Voter\AsVoterHighPriority;
use Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Fixtures\Voter\AsVoterMediumPriority;
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
        $container = new ContainerBuilder();
        $container
            ->register('security.access.decision_manager', AccessDecisionManager::class)
            ->addArgument([])
        ;

        $compilerPass = new AddSecurityVotersPass();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No security voters found. You need to tag at least one with "security.voter".');

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

    public function testThatSecurityVotersWithAsVoterAttributeAreProcessedInPriorityOrder()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);

        $container
            ->register('security.access.decision_manager', AccessDecisionManager::class)
            ->addArgument([])
        ;

        // Register voters - attributes are read and applied as tags
        $this->registerVoterWithAttribute($container, 'attribute_highest_prio', AsVoterHighPriority::class);
        $this->registerVoterWithAttribute($container, 'attribute_medium_prio', AsVoterMediumPriority::class);
        $this->registerVoterWithAttribute($container, 'attribute_default_prio', AsVoterDefaultPriority::class);

        // Manual voter with explicit priority tag
        $container
            ->register('manual_tag_voter', Voter::class)
            ->addTag('security.voter', ['priority' => 150])
        ;

        // Process
        $compilerPass = new AddSecurityVotersPass();
        $compilerPass->process($container);

        // Verify order
        $definition = $container->getDefinition('security.access.decision_manager');
        $argument = $definition->getArgument(0);
        $refs = $argument->getValues();

        $this->assertEquals(new Reference('attribute_highest_prio'), $refs[0]);
        $this->assertEquals(new Reference('manual_tag_voter'), $refs[1]);
        $this->assertEquals(new Reference('attribute_medium_prio'), $refs[2]);
        $this->assertEquals(new Reference('attribute_default_prio'), $refs[3]);
        $this->assertCount(4, $refs);
    }

    public function testThatAsVoterAttributeOverridesOtherAutoConfigurations()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);

        $container
            ->register('security.access.decision_manager', AccessDecisionManager::class)
            ->addArgument([])
        ;

        $this->registerVoterWithAttribute($container, 'voter_with_attribute', AsVoterHighPriority::class);

        $container->register('voter_with_both', AsVoterMediumPriority::class)
            ->addTag('security.voter', ['priority' => 250]);

        // override the manual tag
        $this->registerVoterWithAttribute($container, 'voter_with_both', AsVoterMediumPriority::class);

        // Process
        $compilerPass = new AddSecurityVotersPass();
        $compilerPass->process($container);

        // Verify order
        $definition = $container->getDefinition('security.access.decision_manager');
        $argument = $definition->getArgument(0);
        $refs = $argument->getValues();

        $this->assertEquals(new Reference('voter_with_attribute'), $refs[0]);
        $this->assertEquals(new Reference('voter_with_both'), $refs[1]);
        $this->assertCount(2, $refs);
    }

    /**
     * Simulates attribute autoconfiguration
     * Reads the AsVoter attribute and adds the 'security.voter' tag.
     */
    private function registerVoterWithAttribute(ContainerBuilder $container, string $id, string $class): void
    {
        $container->register($id, $class);

        $reflectionClass = new \ReflectionClass($class);
        $attributes = $reflectionClass->getAttributes(AsVoter::class);

        if (!empty($attributes)) {
            $asVoter = $attributes[0]->newInstance();
            $container->getDefinition($id)->addTag('security.voter', ['priority' => $asVoter->priority]);
        }
    }

    public function testThatVotersAreTraceableInDebugMode()
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

        $def1 = $container->getDefinition('.debug.security.voter.voter1');
        $this->assertNull($def1->getDecoratedService(), 'voter1: should not be decorated');
        $this->assertEquals(new Reference('voter1'), $def1->getArgument(0), 'voter1: wrong argument');

        $def2 = $container->getDefinition('.debug.security.voter.voter2');
        $this->assertNull($def2->getDecoratedService(), 'voter2: should not be decorated');
        $this->assertEquals(new Reference('voter2'), $def2->getArgument(0), 'voter2: wrong argument');

        $voters = $container->findTaggedServiceIds('security.voter');
        $this->assertCount(2, $voters, 'Incorrect count of voters');
    }

    public function testThatVotersAreNotTraceableWithoutDebugMode()
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
        $message = '"stdClass" must implement the "Symfony\Component\Security\Core\Authorization\Voter\VoterInterface" when used as a voter.';

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
