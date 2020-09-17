<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Exception;

/**
 * This exception is thrown if there where too many failed login attempts in
 * this session.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class TooManyLoginAttemptsAuthenticationException extends AuthenticationException
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey(): string
    {
        return 'Too many failed login attempts, please try again later.';
    }
}
