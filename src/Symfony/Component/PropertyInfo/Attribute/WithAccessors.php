<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Attribute;

use Symfony\Component\PropertyInfo\Exception\LogicException;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class WithAccessors
{
    public function __construct(
        public readonly ?string $getter = null,
        public readonly ?string $setter = null,
        public readonly ?string $adder = null,
        public readonly ?string $remover = null,
    ) {
        if (!($this->getter || $this->setter || $this->adder || $this->remover)) {
            throw new LogicException('At least one of "getter", "setter", "adder", or "remover" must be defined.');
        }
        if ($this->adder xor $this->remover) {
            throw new LogicException('Both "adder" and "remover" must be defined when either is set.');
        }
    }
}
