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

abstract class InvalidUidException extends \InvalidArgumentException
{
    public function __construct(
        private readonly string $value,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
