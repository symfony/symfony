<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\Token\Storage;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * TokenStorage contains a TokenInterface.
 *
 * It gives access to the token representing the current user authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class TokenStorage implements TokenStorageInterface, ResetInterface
{
    private ?TokenInterface $token = null;
    private ?\Closure $initializer = null;

    public function getToken(): ?TokenInterface
    {
        if ($initializer = $this->initializer) {
            $this->initializer = null;
            $initializer();
        }

        return $this->token;
    }

    public function setToken(TokenInterface $token = null)
    {
        if (1 > \func_num_args()) {
            trigger_deprecation('symfony/security-core', '6.2', 'Calling "%s()" without any arguments is deprecated, pass null explicitly instead.', __METHOD__);
        }

        if ($token) {
            // ensure any initializer is called
            $this->getToken();
        }

        $this->initializer = null;
        $this->token = $token;
    }

    public function setInitializer(?callable $initializer): void
    {
        $this->initializer = null === $initializer ? null : $initializer(...);
    }

    public function reset()
    {
        $this->setToken(null);
    }
}
