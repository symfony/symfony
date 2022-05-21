<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Passport\Badge;

use Lcobucci\JWT\Token;
use LogicException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\EventListener\TokenUserLoaderListener;

/**
 * @author Vincent Chalamon <vincentchalamon@gmail.com>
 *
 * @final
 */
class TokenBadge extends UserBadge
{
    private readonly Token $payload;

    private ?UserInterface $user = null;

    public function __construct(Token $token, string $userIdentifier, callable $userLoader = null)
    {
        parent::__construct($userIdentifier, $userLoader);

        $this->payload = $token;
    }

    public function getToken(): Token
    {
        return $this->payload;
    }

    /**
     * @throws AuthenticationException when the user cannot be found
     */
    public function getUser(): UserInterface
    {
        if (!isset($this->user)) {
            if (null === $this->getUserLoader()) {
                throw new LogicException(sprintf('No user loader is configured, did you forget to register the "%s" listener?', TokenUserLoaderListener::class));
            }

            $this->user = ($this->getUserLoader())($this->getUserIdentifier(), $this->getToken());
            if (!$this->user instanceof UserInterface) {
                throw new AuthenticationServiceException(sprintf('The user provider must return a UserInterface object, "%s" given.', get_debug_type($this->user)));
            }
        }

        return $this->user;
    }
}
