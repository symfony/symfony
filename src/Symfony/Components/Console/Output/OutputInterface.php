<?php

namespace Symfony\Components\Console\Output;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * OutputInterface is the interface implemented by all Output classes.
 *
 * @package    symfony
 * @subpackage console
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface OutputInterface
{
  /**
   * Writes a message to the output.
   *
   * @param string|array $messages The message as an array of lines of a single string
   * @param integer      $type     The type of output
   */
  public function write($messages, $type = 0);

  /**
   * Sets the verbosity of the output.
   *
   * @param integer $level The level of verbosity
   */
  public function setVerbosity($level);

  /**
   * Sets the decorated flag.
   *
   * @param Boolean $decorated Whether to decorated the messages or not
   */
  public function setDecorated($decorated);
}
