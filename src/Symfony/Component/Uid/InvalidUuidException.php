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

final class InvalidUuidException extends \InvalidArgumentException
{
    public function __construct(
        private readonly int $type,
        private readonly string $value,
    ) {
        parent::__construct(\sprintf('Invalid UUID%s: "%s".', $this->type ? 'v'.$this->type : '', $this->value));
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
