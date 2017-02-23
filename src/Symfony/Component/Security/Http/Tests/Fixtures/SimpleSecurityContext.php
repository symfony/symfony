<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Fixtures;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class SimpleSecurityContext implements AuthorizationCheckerInterface, TokenStorageInterface
{
    protected $token;

    public function getToken()
    {
        return $this->token;
    }

    public function setToken(TokenInterface $token = null)
    {
        $this->token = $token;
    }

    public function isGranted($attributes, $object = null)
    {
        return true;
    }
}
