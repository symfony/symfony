<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Tests\Fixtures\Core;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\SecurityContextInterface;

class SimpleSecurityContext implements SecurityContextInterface
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
