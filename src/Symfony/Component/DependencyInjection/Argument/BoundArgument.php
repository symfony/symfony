<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Argument;

/**
 * @author Guilhem Niot <guilhem.niot@gmail.com>
 */
final class BoundArgument implements ArgumentInterface
{
    public const SERVICE_BINDING = 0;
    public const DEFAULTS_BINDING = 1;
    public const INSTANCEOF_BINDING = 2;

    private static int $sequence = 0;

    private ?int $identifier = null;
    private ?bool $used = null;

    public function __construct(
        private mixed $value,
        bool $trackUsage = true,
        private int $type = 0,
        private ?string $file = null,
    ) {
        if ($trackUsage) {
            $this->identifier = ++self::$sequence;
        } else {
            $this->used = true;
        }
    }

    public function getValues(): array
    {
        return [$this->value, $this->identifier, $this->used, $this->type, $this->file];
    }

    public function setValues(array $values): void
    {
        if (5 === \count($values)) {
            [$this->value, $this->identifier, $this->used, $this->type, $this->file] = $values;
        } else {
            [$this->value, $this->identifier, $this->used] = $values;
        }
    }
}
