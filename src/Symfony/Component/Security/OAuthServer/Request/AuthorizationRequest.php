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
final class AuthorizationRequest extends AbstractRequest
{
    /**
     * {@inheritdoc}
     */
    public function returnAsReadOnly(): array
    {
        return [
            'client_id' => $this->getValue('client_id'),
            'response_type' => $this->getValue('response_type'),
            'redirect_uri' => $this->getValue('redirect_uri'),
            'scope' => $this->getValue('scope'),
            'state' => $this->getValue('state'),
        ];
    }
}
