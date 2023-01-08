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
    public function __construct(string|\UnitEnum $name, string|array|\UnitEnum $froms, string|array|\UnitEnum $tos)
    {
        $this->name = $name;
        if($froms instanceof \UnitEnum) {
            $this->froms = [$froms->name];
        }else{
            $this->froms = (array)$froms;
        }

        if($tos instanceof \UnitEnum) {
            $this->tos = [$tos->name];
        }else{
            $this->tos = (array)$tos;
        }
    }

    public function getName(): string
    {
        return $this->name instanceof \UnitEnum ? $this->name->name : $this->name;
    }

    /**
     * @return string[]
     */
    public function getFroms(): array
    {
        return array_map(static function (string|\UnitEnum $from) {
            return $from instanceof \UnitEnum ? $from->name : $from;
        }, $this->froms);
    }

    /**
     * @return string[]
     */
    public function getTos(): array
    {
        return array_map(static function (string|\UnitEnum $to) {
            return $to instanceof \UnitEnum ? $to->name : $to;
        }, $this->tos);
    }
}
