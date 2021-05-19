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

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Adds the current security token to the log entry.
 *


 */
class TokenProcessor extends AbstractTokenProcessor
{
    /**
     * {@inheritdoc}
     */
    protected function getKey(): string
    {
        return 'token';
    }

    /**
     * {@inheritdoc}
     */
    protected function getToken(): ?TokenInterface
    {
        return $this->tokenStorage->getToken();
    }
}
