<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\OAuthServer\Response;

use Symfony\Component\Security\OAuthServer\Bridge\Psr7Trait;
use Symfony\Component\Security\OAuthServer\Request\AbstractRequest;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractResponse
{
    use Psr7Trait;

    protected $options = [];

    public static function createFromRequest(AbstractRequest $request)
    {
    }

    public function getValue($key, $default = null)
    {
        return \array_key_exists($key, $this->options) ? $this->options[$key] : $default;
    }
}
