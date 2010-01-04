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

abstract class LimeTrace
{
  static public function findCaller($class)
  {
    $traces = debug_backtrace();
    $result = array($traces[0]['file'], $traces[0]['line']);

    $t = array_reverse($traces);
    foreach ($t as $trace)
    {
      if (isset($trace['object']) && isset($trace['file']) && isset($trace['line']))
      {
        $reflection = new ReflectionClass($trace['object']);

        if ($reflection->getName() == $class || $reflection->isSubclassOf($class))
        {
          $result = array($trace['file'], $trace['line']);
          break;
        }
      }
    }

    // remove .test suffix which is added in case of annotated tests
    if (substr($result[0], -5) == '.test')
    {
      $result[0] = substr($result[0], 0, -5);
    }

    return $result;
  }
}