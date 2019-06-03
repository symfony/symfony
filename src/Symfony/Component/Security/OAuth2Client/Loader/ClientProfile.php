<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\OAuth2Client\Loader;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
class ClientProfile
{
    private $content = [];

    public function __construct(array $content = [])
    {
        $this->content = $content;
    }

    public function getContent(): array
    {
        return $this->content;
    }

    public function get(string $key, $default = null)
    {
        return \array_key_exists($key, $this->content) ? $this->content[$key] : $default;
    }
}
