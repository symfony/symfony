<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Firewall;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\AccessMapInterface;
use Symfony\Component\Security\Http\Event\LazyResponseEvent;

/**
 * AccessListener enforces access control rules.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class AccessListener extends AbstractListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private AccessDecisionManagerInterface $accessDecisionManager,
        private AccessMapInterface $map,
        bool $exceptionOnNoToken = false,
    ) {
        if (false !== $exceptionOnNoToken) {
            throw new \LogicException(\sprintf('Argument $exceptionOnNoToken of "%s()" must be set to "false".', __METHOD__));
        }
    }

    public function supports(Request $request): ?bool
    {
        [$attributes] = $this->map->getPatterns($request);
        $request->attributes->set('_access_control_attributes', $attributes);

        if ($attributes && [AuthenticatedVoter::PUBLIC_ACCESS] !== $attributes) {
            return true;
        }

        return null;
    }

    /**
     * Handles access authorization.
     *
     * @throws AccessDeniedException
     */
    public function authenticate(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $attributes = $request->attributes->get('_access_control_attributes');
        $request->attributes->remove('_access_control_attributes');

        if (!$attributes || (
            [AuthenticatedVoter::PUBLIC_ACCESS] === $attributes && $event instanceof LazyResponseEvent
        )) {
            return;
        }

        $token = $this->tokenStorage->getToken() ?? new NullToken();

        if (method_exists($this->accessDecisionManager, 'getDecision')) {
            $decision = $this->accessDecisionManager->getDecision($token, $attributes, $request, true);
        } else {
            $decision = new AccessDecision(
                $this->accessDecisionManager->decide($token, $attributes, $request, true)
                    ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED
            );
        }

        if ($decision->isDenied()) {
            throw $this->createAccessDeniedException($request, $attributes, $decision);
        }
    }

    private function createAccessDeniedException(Request $request, array $attributes, AccessDecision $accessDecision): AccessDeniedException
    {
        $exception = new AccessDeniedException();
        $exception->setAttributes($attributes);
        $exception->setSubject($request);
        $exception->setAccessDecision($accessDecision);

        return $exception;
    }

    public static function getPriority(): int
    {
        return -255;
    }
}
