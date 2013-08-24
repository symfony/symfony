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
 * CredentialsExpiredException is thrown when the user account credentials have expired.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexander <iam.asm89@gmail.com>
 *
 * @since v2.0.0
 */
class CredentialsExpiredException extends AccountStatusException
{
    /**
     * {@inheritDoc}
     *
     * @since v2.2.0
     */
    public function getMessageKey()
    {
        return 'Credentials have expired.';
    }
}
