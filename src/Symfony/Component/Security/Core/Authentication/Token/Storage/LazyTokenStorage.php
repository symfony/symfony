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

/**
 * Lazily populates a token storage.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @final
 */
class LazyTokenStorage implements TokenStorageInterface
{
    private $storage;
    private $initializer;

    public function __construct(TokenStorageInterface $storage)
    {
        $this->storage = $storage;
    }

    public function setInitializer(\Closure $initializer)
    {
        $this->initializer = $initializer;
    }

    /**
     * {@inheritdoc}
     */
    public function getToken()
    {
        if ($initializer = $this->initializer) {
            $this->initializer = null;
            $initializer();
        }

        return $this->storage->getToken();
    }

    /**
     * {@inheritdoc}
     */
    public function setToken(TokenInterface $token = null)
    {
        $this->initializer = null;
        $this->storage->setToken($token);
    }
}
