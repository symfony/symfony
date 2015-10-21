<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class RecursiveCallbackFilterIterator extends CallbackFilterIterator implements RecursiveIterator
{
    private $iterator;
    private $callback;

    public function __construct(RecursiveIterator $iterator, $callback)
    {
        $this->iterator = $iterator;
        $this->callback = $callback;
        parent::__construct($iterator, $callback);
    }

    public function hasChildren()
    {
        return $this->iterator->hasChildren();
    }

    public function getChildren()
    {
        return new static($this->iterator->getChildren(), $this->callback);
    }
}
