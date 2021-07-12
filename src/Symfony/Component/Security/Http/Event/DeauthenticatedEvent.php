<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Event;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Deauthentication happens in case the user has changed when trying to
 * refresh the token.
 *
 * Use {@see TokenDeauthenticatedEvent} if you want to cover all cases where
 * a session is deauthenticated.
 *
 * @author Hamza Amrouche <hamza.simperfit@gmail.com>
 *
 * @deprecated since Symfony 5.4, use TokenDeauthenticatedEvent instead
 */
final class DeauthenticatedEvent extends Event
{
    private $originalToken;
    private $refreshedToken;

    public function __construct(TokenInterface $originalToken, TokenInterface $refreshedToken, bool $triggerDeprecation = true)
    {
        if ($triggerDeprecation) {
            @trigger_deprecation('symfony/security-http', '5.4', 'Class "%s" is deprecated, use "%s" instead.', __CLASS__, TokenDeauthenticatedEvent::class);
        }

        $this->originalToken = $originalToken;
        $this->refreshedToken = $refreshedToken;
    }

    public function getRefreshedToken(): TokenInterface
    {
        @trigger_deprecation('symfony/security-http', '5.4', 'Class "%s" is deprecated, use "%s" instead.', __CLASS__, TokenDeauthenticatedEvent::class);

        return $this->refreshedToken;
    }

    public function getOriginalToken(): TokenInterface
    {
        @trigger_deprecation('symfony/security-http', '5.4', 'Class "%s" is deprecated, use "%s" instead.', __CLASS__, TokenDeauthenticatedEvent::class);

        return $this->originalToken;
    }
}
