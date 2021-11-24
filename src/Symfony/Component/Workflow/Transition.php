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
    private string $name;
    private array $froms;
    private array $tos;

    /**
     * @param string|string[] $froms
     * @param string|string[] $tos
     */
    public function __construct(string $name, string|\UnitEnum|array $froms, string|\UnitEnum|array $tos)
    {
        $this->name = $name;
        $this->froms = \is_array($froms) ? $froms : [$froms];
        $this->tos = \is_array($tos) ? $tos : [$tos];
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string[]
     */
    public function getFroms(): array
    {
        return $this->froms;
    }

    /**
     * @return string[]
     */
    public function getTos(): array
    {
        return $this->tos;
    }
}
