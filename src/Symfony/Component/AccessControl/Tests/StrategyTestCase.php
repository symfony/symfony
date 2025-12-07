<?php

namespace Symfony\Component\AccessControl\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AccessControl\AccessControlManager;
use Symfony\Component\AccessControl\ExpressionLanguage;
use Symfony\Component\AccessControl\Strategy\AffirmativeStrategy;
use Symfony\Component\AccessControl\Strategy\ConsensusStrategy;
use Symfony\Component\AccessControl\Strategy\UnanimousStrategy;
use Symfony\Component\AccessControl\Voter\ABAC\AuthenticatedVoter;
use Symfony\Component\AccessControl\Voter\Expression\ExpressionVoter;
use Symfony\Component\AccessControl\Voter\RBAC\RoleHierarchyVoter;
use Symfony\Component\AccessControl\Voter\RBAC\RoleVoter;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

abstract class StrategyTestCase extends TestCase
{
    private ?AccessControlManager $accessControlManager = null;
    private ?FakeEventDispatcher $eventDispatcher = null;
    private ?TokenStorageInterface $tokenStorage = null;

    protected function getTokenStorage(): TokenStorageInterface
    {
        if ($this->tokenStorage === null) {
            $this->tokenStorage = new FakeTokenStorage();
        }

        return $this->tokenStorage;
    }

    protected function getAccessControlManager(): AccessControlManager
    {
        if ($this->accessControlManager === null) {
            $this->accessControlManager = new AccessControlManager(
                [
                    new AffirmativeStrategy(),
                    new ConsensusStrategy(),
                    new UnanimousStrategy(),
                ],
                [
                    new ExpressionVoter(
                        new ExpressionLanguage(),
                        new AuthenticationTrustResolver(),
                        $this->getRoleHierarchy(),
                    ),
                    new RoleVoter(),
                    new RoleHierarchyVoter($this->getRoleHierarchy()),
                    new AuthenticatedVoter(
                        new AuthenticationTrustResolver(),
                        $this->getTokenStorage()
                    ),
                ],
                dispatcher: $this->getEventDispatcher(),
            );
        }

        return $this->accessControlManager;
    }

    protected function getEventDispatcher(): FakeEventDispatcher
    {
        if ($this->eventDispatcher === null) {
            $this->eventDispatcher = new FakeEventDispatcher();
        }

        return $this->eventDispatcher;
    }

    protected function getRoleHierarchy(): RoleHierarchyInterface
    {
        return new RoleHierarchy([
            'ROLE_ADMIN' => ['ROLE_USER'],
            'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH'],
        ]);
    }
}
