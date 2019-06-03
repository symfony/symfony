<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\OAuthServer\Request;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class AccessTokenRequest extends AbstractRequest
{
    /**
     * {@inheritdoc}
     */
    public function returnAsReadOnly(): array
    {
        $request = [];

        if ('authorization_code' === $this->getValue('grant_type')) {
            $request = [
                'grant_type' => $this->getValue('grant_type'),
                'code' => $this->getValue('code'),
                'redirect_uri' => $this->getValue('redirect_uri'),
                'client_id' => $this->getValue('client_id'),
            ];
        }

        if ('password' === $this->getValue('grant_type')) {
            $request = [
                'grant_type' => $this->getValue('grant_type'),
                'username' => $this->getValue('username'),
                'password' => $this->getValue('password'),
                'scope' => $this->getValue('scope'),
            ];
        }

        if ('client_credentials' === $this->getValue('grant_type')) {
            $request = [
                'grant_type' => $this->getValue('grant_type'),
                'scope' => $this->getValue('scope'),
            ];
        }

        return $request;
    }
}
