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

/**
 * Provides an interface to execute PHP code or files.
 *
 * @package    lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id: LimeShell.php 24323 2009-11-24 11:27:51Z bschussek $
 */
abstract class LimeShell
{
  const
    SUCCESS = 0,
    FAILED  = 1,
    UNKNOWN = 255;

  protected static
    $executable = null;

  /**
   * Sets the preferred PHP executable.
   *
   * @param string $executable
   */
  public static function setExecutable($executable)
  {
    self::$executable = $executable;
  }

  /**
   * Tries to find the system's PHP executable and returns it.
   *
   * @return string
   */
  public static function getExecutable()
  {
    if (is_null(self::$executable))
    {
      if (getenv('PHP_PATH'))
      {
        self::$executable = getenv('PHP_PATH');

        if (!is_executable(self::$executable))
        {
          throw new Exception('The defined PHP_PATH environment variable is not a valid PHP executable.');
        }
      }
      else
      {
        self::$executable = PHP_BINDIR.DIRECTORY_SEPARATOR.'php';
      }
    }

    if (!is_executable(self::$executable))
    {
      $path = getenv('PATH') ? getenv('PATH') : getenv('Path');
      $extensions = DIRECTORY_SEPARATOR == '\\' ? (getenv('PATHEXT') ? explode(PATH_SEPARATOR, getenv('PATHEXT')) : array('.exe', '.bat', '.cmd', '.com')) : array('');
      foreach (array('php5', 'php') as $executable)
      {
        foreach ($extensions as $extension)
        {
          foreach (explode(PATH_SEPARATOR, $path) as $dir)
          {
            $file = $dir.DIRECTORY_SEPARATOR.$executable.$extension;
            if (is_executable($file))
            {
              self::$executable = $file;
              break 3;
            }
          }
        }
      }

      if (!is_executable(self::$executable))
      {
        throw new Exception("Unable to find PHP executable.");
      }
    }

    return self::$executable;
  }

  /**
   * Parses the given CLI arguments and returns an array of options.
   *
   * @param  array $arguments
   * @return array
   */
  public static function parseArguments(array $arguments)
  {
    $options = array();

    foreach ($GLOBALS['argv'] as $parameter)
    {
      if (preg_match('/^--([a-zA-Z\-]+)=(.+)$/', $parameter, $matches))
      {
        if (in_array($matches[2], array('true', 'false')))
        {
          $matches[2] = eval($matches[2]);
        }

        $options[$matches[1]] = $matches[2];
      }
      else if (preg_match('/^--([a-zA-Z\-]+)$/', $parameter, $matches))
      {
        $options[$matches[1]] = true;
      }
    }

    return $options;
  }
}