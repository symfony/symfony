<?php

/*
 * This file is part of the Lime framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * (c) Bernhard Schussek <bernhard.schussek@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

class LimeTesterException extends LimeTesterObject
{
  public function __construct(Exception $exception)
  {
    parent::__construct($exception);

    unset($this->value['file']);
    unset($this->value['line']);
    unset($this->value['trace']);
    unset($this->value['string']); // some internal property of Exception
  }
}