<?php

namespace Symfony\Components\Templating\Loader;

use Symfony\Components\Templating\DebuggerInterface;

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Loader is the base class for all template loader classes.
 *
 * @package    symfony
 * @subpackage templating
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id$
 */
abstract class Loader implements LoaderInterface
{
  protected
    $debugger = null;

  /**
   * Sets the debugger to use for this loader.
   *
   * @param DebuggerInterface $debugger A debugger instance
   */
  public function setDebugger(DebuggerInterface $debugger)
  {
    $this->debugger = $debugger;
  }
}
