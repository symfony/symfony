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

use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Adds the original security token to the log entry.
 *
 * @author Igor Timoshenko <igor.timoshenko@i.ua>
 *
 * @final since Symfony 6.1
 */
class SwitchUserTokenProcessor extends AbstractTokenProcessor
{
    protected function getKey(): string
    {
        return 'impersonator_token';
    }

    protected function getToken(): ?TokenInterface
    {
        $token = $this->tokenStorage->getToken();

        if ($token instanceof SwitchUserToken) {
            return $token->getOriginalToken();
        }

        return null;
    }
}
