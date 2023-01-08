<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Transition
{
    private string|\UnitEnum $name;
    private array $froms;
    private array $tos;

    /**
     * @param string|string[] $froms
     * @param string|string[] $tos
     */
    public function __construct(string $name, string|array|\UnitEnum $froms, string|array|\UnitEnum $tos)
    {
        $this->name = $name;
        if ($froms instanceof \UnitEnum) {
            $this->froms = [$froms];
        } else {
            $this->froms = (array)$froms;
        }

        if ($tos instanceof \UnitEnum) {
            $this->tos = [$tos];
        } else {
            $this->tos = (array)$tos;
        }
    }

    public function getName(): string
    {
        if ($this->name instanceof \BackedEnum) {
            return $this->name->value;
        }
        if ($this->name instanceof \UnitEnum) {
            return $this->name->name;
        }

        return $this->name;
    }

    /**
     * @return string[]
     */
    public function getFroms(): array
    {
        return array_map(static function (string|\UnitEnum $from) {
            if ($from instanceof \BackedEnum) {
                return $from->value;
            }
            if ($from instanceof \UnitEnum) {
                return $from->name;
            }

            return $from;
        }, $this->froms);
    }

    /**
     * @return string[]
     */
    public function getTos(): array
    {
        return array_map(static function (string|\UnitEnum $to) {
            if ($to instanceof \BackedEnum) {
                return $to->value;
            }
            if ($to instanceof \UnitEnum) {
                return $to->name;
            }

            return $to;
        }, $this->tos);
    }
}
