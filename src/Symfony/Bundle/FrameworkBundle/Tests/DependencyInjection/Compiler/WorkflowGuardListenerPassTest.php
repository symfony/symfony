<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\WorkflowGuardListenerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Workflow\EventListener\GuardListener;

class WorkflowGuardListenerPassTest extends TestCase
{
    private $container;
    private $compilerPass;

    protected function setUp()
    {
        $this->container = new ContainerBuilder();
        $this->container->register('foo.listener.guard', GuardListener::class);
        $this->container->register('bar.listener.guard', GuardListener::class);
        $this->compilerPass = new WorkflowGuardListenerPass();
    }

    public function testListenersAreNotRemovedIfParameterIsNotSet()
    {
        $this->compilerPass->process($this->container);

        $this->assertTrue($this->container->hasDefinition('foo.listener.guard'));
        $this->assertTrue($this->container->hasDefinition('bar.listener.guard'));
    }

    public function testParameterIsRemovedWhenThePassIsProcessed()
    {
        $this->container->setParameter('workflow.has_guard_listeners', array('foo.listener.guard', 'bar.listener.guard'));

        try {
            $this->compilerPass->process($this->container);
        } catch (LogicException $e) {
            // Here, we are not interested in the exception handling. This is tested further down.
        }

        $this->assertFalse($this->container->hasParameter('workflow.has_guard_listeners'));
    }

    public function testListenersAreNotRemovedIfAllDependenciesArePresent()
    {
        $this->container->setParameter('workflow.has_guard_listeners', array('foo.listener.guard', 'bar.listener.guard'));
        $this->container->register('security.token_storage', TokenStorageInterface::class);
        $this->container->register('security.authorization_checker', AuthorizationCheckerInterface::class);
        $this->container->register('security.authentication.trust_resolver', AuthenticationTrustResolverInterface::class);
        $this->container->register('security.role_hierarchy', RoleHierarchy::class);

        $this->compilerPass->process($this->container);

        $this->assertTrue($this->container->hasDefinition('foo.listener.guard'));
        $this->assertTrue($this->container->hasDefinition('bar.listener.guard'));
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\LogicException
     * @expectedExceptionMessage The "security.token_storage" service is needed to be able to use the workflow guard listener.
     */
    public function testListenersAreRemovedIfTheTokenStorageServiceIsNotPresent()
    {
        $this->container->setParameter('workflow.has_guard_listeners', array('foo.listener.guard', 'bar.listener.guard'));
        $this->container->register('security.authorization_checker', AuthorizationCheckerInterface::class);
        $this->container->register('security.authentication.trust_resolver', AuthenticationTrustResolverInterface::class);
        $this->container->register('security.role_hierarchy', RoleHierarchy::class);

        $this->compilerPass->process($this->container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\LogicException
     * @expectedExceptionMessage The "security.authorization_checker" service is needed to be able to use the workflow guard listener.
     */
    public function testListenersAreRemovedIfTheAuthorizationCheckerServiceIsNotPresent()
    {
        $this->container->setParameter('workflow.has_guard_listeners', array('foo.listener.guard', 'bar.listener.guard'));
        $this->container->register('security.token_storage', TokenStorageInterface::class);
        $this->container->register('security.authentication.trust_resolver', AuthenticationTrustResolverInterface::class);
        $this->container->register('security.role_hierarchy', RoleHierarchy::class);

        $this->compilerPass->process($this->container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\LogicException
     * @expectedExceptionMessage The "security.authentication.trust_resolver" service is needed to be able to use the workflow guard listener.
     */
    public function testListenersAreRemovedIfTheAuthenticationTrustResolverServiceIsNotPresent()
    {
        $this->container->setParameter('workflow.has_guard_listeners', array('foo.listener.guard', 'bar.listener.guard'));
        $this->container->register('security.token_storage', TokenStorageInterface::class);
        $this->container->register('security.authorization_checker', AuthorizationCheckerInterface::class);
        $this->container->register('security.role_hierarchy', RoleHierarchy::class);

        $this->compilerPass->process($this->container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\LogicException
     * @expectedExceptionMessage The "security.role_hierarchy" service is needed to be able to use the workflow guard listener.
     */
    public function testListenersAreRemovedIfTheRoleHierarchyServiceIsNotPresent()
    {
        $this->container->setParameter('workflow.has_guard_listeners', array('foo.listener.guard', 'bar.listener.guard'));
        $this->container->register('security.token_storage', TokenStorageInterface::class);
        $this->container->register('security.authorization_checker', AuthorizationCheckerInterface::class);
        $this->container->register('security.authentication.trust_resolver', AuthenticationTrustResolverInterface::class);

        $this->compilerPass->process($this->container);
    }
}
