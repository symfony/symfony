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

use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractToken
{
    private const DEFAULT_KEYS = [
        'access_token' => 'string',
        'token_type' => 'string',
    ];

    private $options = [];
    private $additionalOptions = [];

    public function __construct(array $keys, array $additionalOptions = [])
    {
        $this->additionalOptions = $additionalOptions;

        $resolver = new OptionsResolver();
        $this->validateAccessToken($resolver);

        $this->options = $resolver->resolve($keys);
    }

    /**
     * Define the required/optionals access_token keys:.
     *
     *  - access_token
     *  - token_type
     */
    protected function validateAccessToken(OptionsResolver $resolver)
    {
        foreach (self::DEFAULT_KEYS as $key => $keyType) {
            $resolver->setDefined($key);
            $resolver->setAllowedTypes($key, $keyType);
        }

        if (0 < \count($this->additionalOptions)) {
            foreach ($this->additionalOptions as $option => $value) {
                $resolver->setDefined($option);
                $resolver->setAllowedTypes($option, $value);
            }
        }
    }

    /**
     * Return a single value (null if not defined).
     */
    public function getTokenValue($key, $default = null)
    {
        return \array_key_exists($key, $this->options) ? $this->options[$key] : $default;
    }
}
