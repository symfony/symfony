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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Workflow\EventListener\GuardExpression;
use Symfony\Component\Workflow\EventListener\GuardListener;
use Symfony\Component\Workflow\EventListener\NoSecurityGuardListener;

class WorkflowGuardListenerPassTest extends TestCase
{
    private $container;
    private $compilerPass;
    private $definition;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->compilerPass = new WorkflowGuardListenerPass();
        $this->definition = new Definition(GuardListener::class, [
            ['workflow.article.guard.request_review' => new Definition(GuardExpression::class)],
            new Reference('workflow.security.expression_language'),
            new Reference('security.token_storage'),
            new Reference('security.authorization_checker'),
            new Reference('security.authentication.trust_resolver'),
            new Reference('security.role_hierarchy'),
            new Reference('validator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);
    }

    public function testDefinitionIsNotChangedIfParameterIsNotSet()
    {
        $this->container->setDefinition('test_guard_listener', $this->definition)->addTag('workflow.guard_listener');

        $this->compilerPass->process($this->container);

        $this->assertFalse($this->container->hasParameter('workflow.has_guard_listeners'));
        $this->assertGuardListenerDefinitionIsNotChanged();
    }

    public function testDefinitionIsChangedIfAllDependenciesArePresent()
    {
        $this->container->setParameter('workflow.has_guard_listeners', true);
        $this->container->register('security.token_storage', TokenStorageInterface::class);
        $this->container->register('security.authorization_checker', AuthorizationCheckerInterface::class);
        $this->container->register('security.authentication.trust_resolver', AuthenticationTrustResolverInterface::class);
        $this->container->register('security.role_hierarchy', RoleHierarchy::class);
        $this->container->register('validator', ValidatorInterface::class);
        $this->container->setDefinition('test_guard_listener', $this->definition)->addTag('workflow.guard_listener');

        $this->compilerPass->process($this->container);

        $this->assertFalse($this->container->hasParameter('workflow.has_guard_listeners'));
        $this->assertGuardListenerDefinitionIsNotChanged();
    }

    public function testDefinitionIsChangedIfTheTokenStorageServiceIsNotPresent()
    {
        $this->container->setParameter('workflow.has_guard_listeners', true);
        $this->container->register('security.authorization_checker', AuthorizationCheckerInterface::class);
        $this->container->register('security.authentication.trust_resolver', AuthenticationTrustResolverInterface::class);
        $this->container->register('security.role_hierarchy', RoleHierarchy::class);
        $this->container->setDefinition('test_guard_listener', $this->definition)->addTag('workflow.guard_listener');

        $this->compilerPass->process($this->container);

        $this->assertFalse($this->container->hasParameter('workflow.has_guard_listeners'));
        $this->assertGuardListenerDefinitionIsChanged();
    }

    public function testDefinitionIsChangedIfTheAuthorizationCheckerServiceIsNotPresent()
    {
        $this->container->setParameter('workflow.has_guard_listeners', true);
        $this->container->register('security.token_storage', TokenStorageInterface::class);
        $this->container->register('security.authentication.trust_resolver', AuthenticationTrustResolverInterface::class);
        $this->container->register('security.role_hierarchy', RoleHierarchy::class);
        $this->container->setDefinition('test_guard_listener', $this->definition)->addTag('workflow.guard_listener');

        $this->compilerPass->process($this->container);

        $this->assertFalse($this->container->hasParameter('workflow.has_guard_listeners'));
        $this->assertGuardListenerDefinitionIsChanged();
    }

    public function testDefinitionIsChangedIfTheAuthenticationTrustResolverServiceIsNotPresent()
    {
        $this->container->setParameter('workflow.has_guard_listeners', true);
        $this->container->register('security.token_storage', TokenStorageInterface::class);
        $this->container->register('security.authorization_checker', AuthorizationCheckerInterface::class);
        $this->container->register('security.role_hierarchy', RoleHierarchy::class);
        $this->container->setDefinition('test_guard_listener', $this->definition)->addTag('workflow.guard_listener');

        $this->compilerPass->process($this->container);

        $this->assertFalse($this->container->hasParameter('workflow.has_guard_listeners'));
        $this->assertGuardListenerDefinitionIsChanged();
    }

    public function testDefinitionIsChangedIfTheRoleHierarchyServiceIsNotPresent()
    {
        $this->container->setParameter('workflow.has_guard_listeners', true);
        $this->container->register('security.token_storage', TokenStorageInterface::class);
        $this->container->register('security.authorization_checker', AuthorizationCheckerInterface::class);
        $this->container->register('security.authentication.trust_resolver', AuthenticationTrustResolverInterface::class);
        $this->container->setDefinition('test_guard_listener', $this->definition)->addTag('workflow.guard_listener');

        $this->compilerPass->process($this->container);

        $this->assertFalse($this->container->hasParameter('workflow.has_guard_listeners'));
        $this->assertGuardListenerDefinitionIsChanged();
    }

    private function assertGuardListenerDefinitionIsNotChanged()
    {
        $guardDefinition = $this->container->getDefinition('test_guard_listener');
        $this->assertSame(GuardListener::class, $guardDefinition->getClass());
        $this->assertCount(7, $guardDefinition->getArguments());
        $this->assertTrue(\is_array($guardDefinition->getArgument(0)) && !empty($guardDefinition->getArgument(0)));
        $this->assertSame('workflow.security.expression_language', (string) $guardDefinition->getArgument(1));
        $this->assertSame('security.token_storage', (string) $guardDefinition->getArgument(2));
        $this->assertSame('security.authorization_checker', (string) $guardDefinition->getArgument(3));
        $this->assertSame('security.authentication.trust_resolver', (string) $guardDefinition->getArgument(4));
        $this->assertSame('security.role_hierarchy', (string) $guardDefinition->getArgument(5));
        $this->assertSame('validator', (string) $guardDefinition->getArgument(6));
    }

    private function assertGuardListenerDefinitionIsChanged()
    {
        $guardDefinition = $this->container->getDefinition('test_guard_listener');
        $this->assertSame(NoSecurityGuardListener::class, $guardDefinition->getClass());
        $this->assertCount(2, $guardDefinition->getArguments());
        $this->assertTrue(\is_array($guardDefinition->getArgument(0)) && !empty($guardDefinition->getArgument(0)));
        $this->assertSame('workflow.no_security.expression_language', (string) $guardDefinition->getArgument(1));
    }
}
