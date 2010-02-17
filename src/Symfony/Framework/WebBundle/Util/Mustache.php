<?php

namespace Symfony\Framework\WebBundle\Util;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

class Mustache
{
  static public function renderFile($file, $parameters)
  {
    $replacer = function ($match) use($parameters)
    {
      return isset($parameters[$match[1]]) ? $parameters[$match[1]] : "{{ $match[0] }}";
    };

    file_put_contents($file, preg_replace_callback('/{{\s*(.+?)\s*}}/', $replacer, file_get_contents($file)));
  }

  static public function renderDir($dir, $parameters)
  {
    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir), \RecursiveIteratorIterator::LEAVES_ONLY) as $file)
    {
      static::renderFile((string) $file, $parameters);
    }
  }
}
