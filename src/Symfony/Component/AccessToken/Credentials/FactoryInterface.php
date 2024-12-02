<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AccessToken\Credentials;

use Symfony\Component\AccessToken\CredentialsInterface;

/**
 * Represent access token credentials.
 *
 * Side concepts such as client identifier, client secret, scope and such
 * you may find in standard OAuth flows are implementation details.
 *
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
interface FactoryInterface
{
    /**
     * Create credentials from URI.
     */
    public function createCredentials(Dsn $dsn): CredentialsInterface;
}
