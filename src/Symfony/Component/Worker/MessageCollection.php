<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Worker;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class MessageCollection implements \IteratorAggregate, \Countable
{
    private $messages = array();

    public function __construct($message = null)
    {
        if ($message) {
            $this->messages[] = $message;
        }
    }

    public function add($message)
    {
        $this->messages[] = $message;
    }

    public function all()
    {
        $all = $this->messages;

        $this->messages = array();

        return $all;
    }

    public function pop()
    {
        return array_shift($this->messages);
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->messages);
    }

    public function count()
    {
        return count($this->messages);
    }
}
