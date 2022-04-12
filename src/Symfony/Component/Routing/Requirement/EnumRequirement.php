<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Requirement;

use Symfony\Component\Routing\Exception\InvalidArgumentException;

final class EnumRequirement implements \Stringable
{
    /**
     * @var string[]
     */
    private readonly array $values;

    /**
     * @template T of \BackedEnum
     * @param class-string<T> $enum
     * @param T ...$cases
     */
    public function __construct(string $enum, \BackedEnum ...$cases)
    {
        if (!\is_subclass_of($enum, \BackedEnum::class, true)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a \BackedEnum class.', $enum));
        }

        foreach ($cases as $case) {
            if (!$case instanceof $enum) {
                throw new InvalidArgumentException(sprintf('"%s::%s" is not a case of "%s".', \get_class($case), $case->name, $enum));
            }
        }

        $this->values = array_unique(array_map(
            static fn (\BackedEnum $e): string => $e->value,
            $cases ?: $enum::cases(),
        ));
    }

    public function __toString(): string
    {
        return implode('|', array_map(preg_quote(...), $this->values));
    }
}
