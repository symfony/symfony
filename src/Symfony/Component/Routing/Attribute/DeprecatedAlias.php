<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Attribute;

/**
 * This class is meant to be used in {@see Route} to define an alias for a route.
 */
class DeprecatedAlias
{
    public function __construct(
        private string $aliasName,
        private string $package,
        private string $version,
        private string $message = '',
    ) {
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getAliasName(): string
    {
        return $this->aliasName;
    }

    public function getPackage(): string
    {
        return $this->package;
    }

    public function getVersion(): string
    {
        return $this->version;
    }
}
