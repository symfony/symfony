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
    private $token;
    private $initializer;

    /**
     * {@inheritdoc}
     */
    public function getToken()
    {
        if ($initializer = $this->initializer) {
            $this->initializer = null;
            $initializer();
        }

        return $this->token;
    }

    /**
     * {@inheritdoc}
     */
    public function setToken(TokenInterface $token = null)
    {
        if (null !== $token && !method_exists($token, 'getRoleNames') && !$token instanceof \PHPUnit\Framework\MockObject\MockObject && !$token instanceof \Prophecy\Prophecy\ProphecySubjectInterface) {
            @trigger_error(sprintf('Not implementing the "%s::getRoleNames()" method in "%s" is deprecated since Symfony 4.3.', TokenInterface::class, \get_class($token)), E_USER_DEPRECATED);
        }

        $this->initializer = null;
        $this->token = $token;
    }

    public function setInitializer(?callable $initializer): void
    {
        $this->initializer = $initializer;
    }

    public function reset()
    {
        $this->setToken(null);
    }
}
