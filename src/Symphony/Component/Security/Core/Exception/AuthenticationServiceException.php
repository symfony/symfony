<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Core\Exception;

/**
 * AuthenticationServiceException is thrown when an authentication request could not be processed due to a system problem.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 * @author Alexander <iam.asm89@gmail.com>
 */
class AuthenticationServiceException extends AuthenticationException
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Authentication request could not be processed due to a system problem.';
    }
}
