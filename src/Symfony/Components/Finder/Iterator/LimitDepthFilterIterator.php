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
 * LimitDepthFilterIterator limits the directory depth.
 *
 * @package    Symfony
 * @subpackage Components_Finder
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class LimitDepthFilterIterator extends \FilterIterator
{
  protected $minDepth = 0;
  protected $maxDepth = INF;
  protected $baseDir;

  /**
   * Constructor.
   *
   * @param \Iterator $iterator The Iterator to filter
   * @param string    $baseDir  The base directory for the depth comparison
   * @param integer   $minDepth The minimum depth
   * @param integer   $maxDepth The maximum depth
   */
  public function __construct(\Iterator $iterator, $baseDir, $minDepth, $maxDepth)
  {
    $this->baseDir  = new \SplFileInfo($baseDir);
    $this->minDepth = (integer) $minDepth;
    $this->maxDepth = (double) $maxDepth;

    parent::__construct($iterator);
  }

  /**
   * Filters the iterator values.
   *
   * @return Boolean true if the value should be kept, false otherwise
   */
  public function accept()
  {
    $fileinfo = $this->getInnerIterator()->current();

    $depth = substr_count($fileinfo->getPath(), DIRECTORY_SEPARATOR) - substr_count($this->baseDir->getPathname(), DIRECTORY_SEPARATOR);

    if ($depth > $this->maxDepth)
    {
      return false;
    }

    if ($depth < $this->minDepth)
    {
      return false;
    }

    return true;
  }
}
