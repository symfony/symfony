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

use Symfony\Component\Workflow\Exception\InvalidArgumentException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Transition
{
    const MATCH_ALL = 'all';
    const MATCH_ONE = 'one';

    private $name;
    private $froms;
    private $tos;
    private $matchType;

    /**
     * @param string          $name
     * @param string|string[] $froms
     * @param string|string[] $tos
     */
    public function __construct($name, $froms, $tos, $matchType = self::MATCH_ALL)
    {
        if (!preg_match('{^[\w\d_-]+$}', $name)) {
            throw new InvalidArgumentException(sprintf('The transition "%s" contains invalid characters.', $name));
        }

        $this->name = $name;
        $this->froms = (array) $froms;
        $this->tos = (array) $tos;
        $this->matchType = $matchType;
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

    public function getMatchType()
    {
        return $this->matchType;
    }
}
