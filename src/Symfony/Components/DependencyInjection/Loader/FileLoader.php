<?php

namespace Symfony\Components\DependencyInjection\Loader;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * FileLoader is the abstract class used by all built-in loaders that are file based.
 *
 * @package    symfony
 * @subpackage dependency_injection
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class FileLoader extends Loader
{
  protected $paths;

  /**
   * Constructor.
   *
   * @param string|array $paths A path or an array of paths where to look for resources
   */
  public function __construct($paths = array())
  {
    if (!is_array($paths))
    {
      $paths = array($paths);
    }

    $this->paths = $paths;
  }

  protected function findFile($file)
  {
    $path = $this->getAbsolutePath($file);
    if (!file_exists($path))
    {
      throw new \InvalidArgumentException(sprintf('The file "%s" does not exist (in: %s).', $file, implode(', ', $this->paths)));
    }

    return $path;
  }

  protected function getAbsolutePath($file, $currentPath = null)
  {
    if (self::isAbsolutePath($file))
    {
      return $file;
    }
    else if (null !== $currentPath && file_exists($currentPath.DIRECTORY_SEPARATOR.$file))
    {
      return $currentPath.DIRECTORY_SEPARATOR.$file;
    }
    else
    {
      foreach ($this->paths as $path)
      {
        if (file_exists($path.DIRECTORY_SEPARATOR.$file))
        {
          return $path.DIRECTORY_SEPARATOR.$file;
        }
      }
    }

    return $file;
  }

  static protected function isAbsolutePath($file)
  {
    if ($file[0] == '/' || $file[0] == '\\' ||
        (strlen($file) > 3 && ctype_alpha($file[0]) &&
         $file[1] == ':' &&
         ($file[2] == '\\' || $file[2] == '/')
        )
       )
    {
      return true;
    }

    return false;
  }
}
