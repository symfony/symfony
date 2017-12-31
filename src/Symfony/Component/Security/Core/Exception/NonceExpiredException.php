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

@trigger_error(sprintf('The %s class and the whole HTTP digest authentication system is deprecated since Symfony 3.4 and will be removed in 4.0.', NonceExpiredException::class), E_USER_DEPRECATED);

/**
 * NonceExpiredException is thrown when an authentication is rejected because
 * the digest nonce has expired.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexander <iam.asm89@gmail.com>
 *
 * @deprecated since 3.4, to be removed in 4.0
 */
class NonceExpiredException extends AuthenticationException
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Digest nonce has expired.';
    }
}
