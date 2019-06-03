<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\OAuth2Client\Token;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ClientGrantToken extends AbstractToken
{
    /**
     * {@inheritdoc}
     */
    public function __construct(array $keys)
    {
        parent::__construct($keys, [
            'expires_in' => ['int', 'null'],
        ]);
    }
}
