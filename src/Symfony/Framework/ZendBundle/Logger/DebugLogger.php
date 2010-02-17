<?php

namespace Symfony\Framework\ZendBundle\Logger;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * 
 *
 * @package    symfony
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class DebugLogger extends \Zend_Log_Writer_Abstract
{
  protected $logs = array();

  public function getLogs()
  {
    return $this->logs;
  }

  /**
   * Write a message to the log.
   *
   * @param  array  $event  event data
   * @return void
   */
  protected function _write($event)
  {
    $this->logs[] = $event;
  }

  static public function factory($config)
  {
  }
}
