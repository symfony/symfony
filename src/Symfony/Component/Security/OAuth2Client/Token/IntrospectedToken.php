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
final class IntrospectedToken extends AbstractToken
{
    /**
     * {@inheritdoc}
     */
    public function __construct(array $keys)
    {
        parent::__construct($keys, [
            'active' => ['bool'],
            'scope' => ['string', 'null'],
            'client_id' => ['string', 'null'],
            'username' => ['string', 'null'],
            'token_type' => ['string', 'null'],
            'exp' => ['int', 'null'],
            'iat' => ['int', 'null'],
            'nbf' => ['int', 'null'],
            'sub' => ['string', 'null'],
            'aud' => ['string', 'null'],
            'iss' => ['string', 'null'],
            'jti' => ['string', 'null'],
        ]);
    }
}
