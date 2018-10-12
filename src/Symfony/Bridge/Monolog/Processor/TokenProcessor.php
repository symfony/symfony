<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Processor;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Adds the current security token to the log entry.
 *
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
class TokenProcessor
{
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function __invoke(array $records)
    {
        $records['extra']['token'] = null;
        if (null !== $token = $this->tokenStorage->getToken()) {
            $records['extra']['token'] = array(
                'username' => $token->getUsername(),
                'authenticated' => $token->isAuthenticated(),
                'roles' => array_map(function ($role) { return $role->getRole(); }, $token->getRoles()),
            );
        }

        return $records;
    }
}
