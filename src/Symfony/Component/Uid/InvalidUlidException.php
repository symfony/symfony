<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uid;

final class InvalidUlidException extends \InvalidArgumentException
{
    public function __construct(
        private readonly string $value,
    ) {
        parent::__construct(\sprintf('Invalid ULID: "%s".', $this->value));
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
