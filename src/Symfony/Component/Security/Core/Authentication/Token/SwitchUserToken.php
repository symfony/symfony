<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\Token;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Token representing a user who temporarily impersonates another one.
 *
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 */
class SwitchUserToken extends UsernamePasswordToken
{
    private $originalToken;
    private $originatedFromUri;

    /**
     * @param UserInterface $user
     * @param string|null   $originatedFromUri The URI where was the user at the switch
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($user, /*string*/ $firewallName, /*array*/ $roles, /*TokenInterface*/ $originalToken, /*string*/ $originatedFromUri = null)
    {
        if (\is_string($roles)) {
            // @deprecated since 5.4, deprecation is triggered by UsernamePasswordToken::__construct()
            $credentials = $firewallName;
            $firewallName = $roles;
            $roles = $originalToken;
            $originalToken = $originatedFromUri;
            $originatedFromUri = \func_num_args() > 5 ? func_get_arg(5) : null;

            parent::__construct($user, $credentials, $firewallName, $roles);
        } else {
            parent::__construct($user, $firewallName, $roles);
        }

        if (!$originalToken instanceof TokenInterface) {
            throw new \TypeError(sprintf('Argument $originalToken of "%s" must be an instance of "%s", "%s" given.', __METHOD__, TokenInterface::class, get_debug_type($originalToken)));
        }

        $this->originalToken = $originalToken;
        $this->originatedFromUri = $originatedFromUri;
    }

    public function getOriginalToken(): TokenInterface
    {
        return $this->originalToken;
    }

    public function getOriginatedFromUri(): ?string
    {
        return $this->originatedFromUri;
    }

    /**
     * {@inheritdoc}
     */
    public function __serialize(): array
    {
        return [$this->originalToken, $this->originatedFromUri, parent::__serialize()];
    }

    /**
     * {@inheritdoc}
     */
    public function __unserialize(array $data): void
    {
        if (3 > \count($data)) {
            // Support for tokens serialized with version 5.1 or lower of symfony/security-core.
            [$this->originalToken, $parentData] = $data;
        } else {
            [$this->originalToken, $this->originatedFromUri, $parentData] = $data;
        }
        $parentData = \is_array($parentData) ? $parentData : unserialize($parentData);
        parent::__unserialize($parentData);
    }
}
