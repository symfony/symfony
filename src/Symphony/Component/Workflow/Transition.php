<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Workflow;

/**
 * @author Fabien Potencier <fabien@symphony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Transition
{
    private $name;
    private $froms;
    private $tos;

    /**
     * @param string          $name
     * @param string|string[] $froms
     * @param string|string[] $tos
     */
    public function __construct(string $name, $froms, $tos)
    {
        $this->name = $name;
        $this->froms = (array) $froms;
        $this->tos = (array) $tos;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getFroms()
    {
        return $this->froms;
    }

    public function getTos()
    {
        return $this->tos;
    }
}
