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
 * CredentialsExpiredException is thrown when the user account credentials have expired.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 * @author Alexander <iam.asm89@gmail.com>
 */
class CredentialsExpiredException extends AccountStatusException
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Credentials have expired.';
    }
}
