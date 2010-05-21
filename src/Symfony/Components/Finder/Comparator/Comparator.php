<?php

namespace Symfony\Components\Finder\Comparator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Comparator.
 *
 * @package    Symfony
 * @subpackage Components_Finder
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com> PHP port
 */
class Comparator
{
    protected $target;
    protected $operator;

    public function setTarget($target)
    {
        $this->target = $target;
    }

    public function setOperator($operator)
    {
        if (!$operator) {
            $operator = '==';
        }

        if (!in_array($operator, array('>', '<', '>=', '<=', '=='))) {
            throw new \InvalidArgumentException(sprintf('Invalid operator "%s".', $operator));
        }

        $this->operator = $operator;
    }

    /**
     * Tests against the target.
     *
     * @param mixed $test A test value
     */
    public function test($test)
    {
        switch ($this->operator) {
            case '>':
                return $test > $this->target;
            case '>=':
                return $test >= $this->target;
            case '<':
                return $test < $this->target;
            case '<=':
                return $test <= $this->target;
        }

        return $test == $this->target;
    }
}
