<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentNameConverter;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Http\Attribute\Security;

/**
 * SecurityListener handles security restrictions on controllers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SecurityAttributeListener implements EventSubscriberInterface
{
    private $argumentNameConverter;
    private $tokenStorage;
    private $authChecker;
    private $language;
    private $trustResolver;
    private $roleHierarchy;
    private $logger;

    public function __construct(ArgumentNameConverter $argumentNameConverter, ExpressionLanguage $language = null, AuthenticationTrustResolverInterface $trustResolver = null, RoleHierarchyInterface $roleHierarchy = null, TokenStorageInterface $tokenStorage = null, AuthorizationCheckerInterface $authChecker = null, LoggerInterface $logger = null)
    {
        $this->argumentNameConverter = $argumentNameConverter;
        $this->tokenStorage = $tokenStorage;
        $this->authChecker = $authChecker;
        $this->language = $language;
        $this->trustResolver = $trustResolver;
        $this->roleHierarchy = $roleHierarchy;
        $this->logger = $logger;
    }

    public function onKernelControllerArguments(KernelEvent $event)
    {
        $controller = $event->getController();

        if (!\is_array($controller) && method_exists($controller, '__invoke')) {
            $controller = [$controller, '__invoke'];
        }

        if (!\is_array($controller)) {
            return;
        }

        $className = \get_class($controller[0]);
        $object = new \ReflectionClass($className);
        $method = $object->getMethod($controller[1]);

        $classConfigurations = array_map(
            function (\ReflectionAttribute $attribute) {
                return $attribute->newInstance();
            },
            $object->getAttributes(Security::class)
        );
        $methodConfigurations = array_map(
            function (\ReflectionAttribute $attribute) {
                return $attribute->newInstance();
            },
            $method->getAttributes(Security::class)
        );
        $configurations = array_merge($methodConfigurations, $classConfigurations);

        if (0 === count($configurations)) {
            return;
        }

        if (null === $this->tokenStorage || null === $this->trustResolver) {
            throw new \LogicException('To use the @Security tag, you need to install the Symfony Security bundle.');
        }

        if (null === $this->tokenStorage->getToken()) {
            throw new AccessDeniedException('No user token or you forgot to put your controller behind a firewall while using a @Security tag.');
        }

        if (null === $this->language) {
            throw new \LogicException('To use the @Security tag, you need to use the Security component 2.4 or newer and install the ExpressionLanguage component.');
        }

        foreach ($configurations as $configuration) {
            if (!$this->language->evaluate($configuration->getExpression(), $this->getVariables($event))) {
                if ($statusCode = $configuration->getStatusCode()) {
                    throw new HttpException($statusCode, $configuration->getMessage());
                }

                throw new AccessDeniedException($configuration->getMessage() ?: sprintf('Expression "%s" denied access.', $configuration->getExpression()));
            }
        }
    }

    // code should be sync with Symfony\Component\Security\Core\Authorization\Voter\ExpressionVoter
    private function getVariables(KernelEvent $event)
    {
        $request = $event->getRequest();
        $token = $this->tokenStorage->getToken();
        $variables = [
            'token' => $token,
            'user' => $token->getUser(),
            'object' => $request,
            'subject' => $request,
            'request' => $request,
            'roles' => $this->getRoles($token),
            'trust_resolver' => $this->trustResolver,
            // needed for the is_granted expression function
            'auth_checker' => $this->authChecker,
        ];

        $controllerArguments = $this->argumentNameConverter->getControllerArguments($event);

        if ($diff = array_intersect(array_keys($variables), array_keys($controllerArguments))) {
            foreach ($diff as $key => $variableName) {
                if ($variables[$variableName] === $controllerArguments[$variableName]) {
                    unset($diff[$key]);
                }
            }

            if ($diff) {
                $singular = 1 === \count($diff);
                if (null !== $this->logger) {
                    $this->logger->warning(sprintf('Controller argument%s "%s" collided with the built-in security expression variables. The built-in value%s are being used for the @Security expression.', $singular ? '' : 's', implode('", "', $diff), $singular ? 's' : ''));
                }
            }
        }

        // controller variables should also be accessible
        return array_merge($controllerArguments, $variables);
    }

    private function getRoles(TokenInterface $token): array
    {
        if (method_exists($this->roleHierarchy, 'getReachableRoleNames')) {
            if (null !== $this->roleHierarchy) {
                $roles = $this->roleHierarchy->getReachableRoleNames($token->getRoleNames());
            } else {
                $roles = $token->getRoleNames();
            }
        } else {
            if (null !== $this->roleHierarchy) {
                $roles = $this->roleHierarchy->getReachableRoles($token->getRoles());
            } else {
                $roles = $token->getRoles();
            }

            $roles = array_map(function ($role) {
                return $role->getRole();
            }, $roles);
        }

        return $roles;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [KernelEvents::CONTROLLER_ARGUMENTS => 'onKernelControllerArguments'];
    }
}
