<?php

namespace Symfony\Components\Finder\Iterator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ChainIterator iterates through several iterators, one at a time.
 *
 * @package    Symfony
 * @subpackage Components_Finder
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ChainIterator implements \Iterator
{
  protected $iterators;
  protected $current;
  protected $cursor;

  /**
   * Constructor.
   *
   * @param array $iterators An array of \Iterator instances
   */
  public function __construct(array $iterators = array())
  {
    $this->iterators = array();
    foreach ($iterators as $iterator)
    {
      $this->attach($iterator);
    }
    $this->rewind();
  }

  public function attach(\Iterator $iterator)
  {
    $this->iterators[] = $iterator;
  }

  public function rewind()
  {
    $this->cursor = 0;
    $this->current = 0;
    foreach ($this->iterators as $iterator)
    {
      $iterator->rewind();
    }
  }

  public function valid()
  {
    if ($this->current > count($this->iterators) - 1)
    {
      return false;
    }

    // still something for the current iterator?
    if ($this->iterators[$this->current]->valid())
    {
      return true;
    }

    // go to the next one
    ++$this->current;

    return $this->valid();
  }

  public function next()
  {
    $this->iterators[$this->current]->next();
  }

  public function current()
  {
    return $this->iterators[$this->current]->current();
  }

  public function key()
  {
    return $this->cursor++;
  }
}
