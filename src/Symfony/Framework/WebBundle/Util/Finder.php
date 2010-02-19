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

/**
 * Allows to build rules to find files and directories.
 *
 * All rules may be invoked several times, except for ->in() method.
 * Some rules are cumulative (->name() for example) whereas others are destructive
 * (most recent value is used, ->maxdepth() method for example).
 *
 * All methods return the current Finder object to allow easy chaining:
 *
 * $files = Finder::type('file')->name('*.php')->in(.);
 *
 * Interface loosely based on perl File::Find::Rule module.
 *
 * @package symfony
 * @author  Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Finder
{
  protected $type                 = 'file';
  protected $names                = array();
  protected $prunes               = array();
  protected $discards             = array();
  protected $execs                = array();
  protected $mindepth             = 0;
  protected $sizes                = array();
  protected $maxdepth             = 1000000;
  protected $relative             = false;
  protected $followLinks          = false;
  protected $sort                 = false;
  protected $ignoreVersionControl = true;

  /**
   * Sets maximum directory depth.
   *
   * Finder will descend at most $level levels of directories below the starting point.
   *
   * @param  int $level
   * @return object current Finder object
   */
  public function maxDepth($level)
  {
    $this->maxdepth = $level;

    return $this;
  }

  /**
   * Sets minimum directory depth.
   *
   * Finder will start applying tests at level $level.
   *
   * @param  int $level
   * @return object current Finder object
   */
  public function minDepth($level)
  {
    $this->mindepth = $level;

    return $this;
  }

  public function getType()
  {
    return $this->type;
  }

  /**
   * Sets the type of elements to returns.
   *
   * @param  string $name  directory or file or any (for both file and directory)
   * @return object new Finder object
   */
  public static function type($name)
  {
    $finder = new self();
    return $finder->setType($name);
  }
  /**
   * Sets the type of elements to returns.
   *
   * @param  string $name  directory or file or any (for both file and directory)
   * @return Finder Current object
   */
  public function setType($name)
  {
    $name = strtolower($name);

    if (substr($name, 0, 3) === 'dir')
    {
      $this->type = 'directory';

      return $this;
    }
    if ($name === 'any')
    {
      $this->type = 'any';

      return $this;
    }

      $this->type = 'file';

    return $this;
  }

  /*
   * glob, patterns (must be //) or strings
   */
  protected function toRegex($str)
  {
    if (preg_match('/^(!)?([^a-zA-Z0-9\\\\]).+?\\2[ims]?$/', $str))
    {
      return $str;
    }

    return Glob::toRegex($str);
  }

  protected function argsToArray($arg_list, $not = false)
  {
    $list = array();
    $nbArgList = count($arg_list);
    for ($i = 0; $i < $nbArgList; $i++)
    {
      if (is_array($arg_list[$i]))
      {
        foreach ($arg_list[$i] as $arg)
        {
          $list[] = array($not, $this->toRegex($arg));
        }
      }
      else
      {
        $list[] = array($not, $this->toRegex($arg_list[$i]));
      }
    }

    return $list;
  }

  /**
   * Adds rules that files must match.
   *
   * You can use patterns (delimited with / sign), globs or simple strings.
   *
   * $finder->name('*.php')
   * $finder->name('/\.php$/') // same as above
   * $finder->name('test.php')
   *
   * @param  list   a list of patterns, globs or strings
   * @return Finder Current object
   */
  public function name()
  {
    $args = func_get_args();
    $this->names = array_merge($this->names, $this->argsToArray($args));

    return $this;
  }

  /**
   * Adds rules that files must not match.
   *
   * @see    ->name()
   * @param  list   a list of patterns, globs or strings
   * @return Finder Current object
   */
  public function notName()
  {
    $args = func_get_args();
    $this->names = array_merge($this->names, $this->argsToArray($args, true));

    return $this;
  }

  /**
   * Adds tests for file sizes.
   *
   * $finder->size('> 10K');
   * $finder->size('<= 1Ki');
   * $finder->size(4);
   *
   * @param  list   a list of comparison strings
   * @return Finder Current object
   */
  public function size()
  {
    $args = func_get_args();
    $numargs = count($args);
    for ($i = 0; $i < $numargs; $i++)
    {
      $this->sizes[] = new NumberCompare($args[$i]);
    }

    return $this;
  }

  /**
   * Traverses no further.
   *
   * @param  list   a list of patterns, globs to match
   * @return Finder Current object
   */
  public function prune()
  {
    $args = func_get_args();
    $this->prunes = array_merge($this->prunes, $this->argsToArray($args));

    return $this;
  }

  /**
   * Discards elements that matches.
   *
   * @param  list   a list of patterns, globs to match
   * @return Finder Current object
   */
  public function discard()
  {
    $args = func_get_args();
    $this->discards = array_merge($this->discards, $this->argsToArray($args));

    return $this;
  }

  /**
   * Ignores version control directories.
   *
   * Currently supports Subversion, CVS, DARCS, Gnu Arch, Monotone, Bazaar-NG, GIT, Mercurial
   *
   * @param  bool   $ignore  falase when version control directories shall be included (default is true)
   *
   * @return Finder Current object
   */
  public function ignoreVersionControl($ignore = true)
  {
    $this->ignoreVersionControl = $ignore;

    return $this;
  }

  /**
   * Returns files and directories ordered by name
   *
   * @return Finder Current object
   */
  public function sortByName()
  {
    $this->sort = 'name';

    return $this;
  }

  /**
   * Returns files and directories ordered by type (directories before files), then by name
   *
   * @return Finder Current object
   */
  public function sortByType()
  {
    $this->sort = 'type';

    return $this;
  }

  /**
   * Executes function or method for each element.
   *
   * Element match if functino or method returns true.
   *
   * $finder->exec('myfunction');
   * $finder->exec(array($object, 'mymethod'));
   *
   * @param  mixed  function or method to call
   * @return Finder Current object
   */
  public function exec()
  {
    $args = func_get_args();
    $numargs = count($args);
    for ($i = 0; $i < $numargs; $i++)
    {
      if (is_array($args[$i]) && !method_exists($args[$i][0], $args[$i][1]))
      {
        throw new \InvalidArgumentException(sprintf('Method "%s" does not exist for object "%s".', $args[$i][1], $args[$i][0]));
      }
      if (!is_array($args[$i]) && !function_exists($args[$i]))
      {
        throw new \InvalidArgumentException(sprintf('Function "%s" does not exist.', $args[$i]));
      }

      $this->execs[] = $args[$i];
    }

    return $this;
  }

  /**
   * Returns relative paths for all files and directories.
   *
   * @return Finder Current object
   */
  public function relative()
  {
    $this->relative = true;

    return $this;
  }

  /**
   * Symlink following.
   *
   * @return Finder Current object
   */
  public function followLinks()
  {
    $this->followLinks = true;

    return $this;
  }

  /**
   * Searches files and directories which match defined rules.
   *
   * @return array list of files and directories
   */
  public function in()
  {
    $files    = array();
    $here_dir = getcwd();

    $finder = clone $this;

    if ($this->ignoreVersionControl)
    {
      $ignores = array('.svn', '_svn', 'CVS', '_darcs', '.arch-params', '.monotone', '.bzr', '.git', '.hg');

      $finder->discard($ignores)->prune($ignores);
    }

    // first argument is an array?
    $numargs  = func_num_args();
    $arg_list = func_get_args();
    if ($numargs === 1 && is_array($arg_list[0]))
    {
      $arg_list = $arg_list[0];
      $numargs  = count($arg_list);
    }

    for ($i = 0; $i < $numargs; $i++)
    {
      $dir = realpath($arg_list[$i]);

      if (!is_dir($dir))
      {
        continue;
      }

      $dir = str_replace('\\', '/', $dir);

      // absolute path?
      if (!self::isPathAbsolute($dir))
      {
        $dir = $here_dir.'/'.$dir;
      }

      $new_files = str_replace('\\', '/', $finder->searchIn($dir));

      if ($this->relative)
      {
        $new_files = str_replace(rtrim($dir, '/').'/', '', $new_files);
      }

      $files = array_merge($files, $new_files);
    }

    if ($this->sort === 'name')
    {
      sort($files);
    }

    return array_unique($files);
  }

  protected function searchIn($dir, $depth = 0)
  {
    if ($depth > $this->maxdepth)
    {
      return array();
    }

    $dir = realpath($dir);

    if ((!$this->followLinks) && is_link($dir))
    {
      return array();
    }

    $files = array();
    $temp_files = array();
    $temp_folders = array();
    if (is_dir($dir))
    {
      $current_dir = opendir($dir);
      while (false !== $entryname = readdir($current_dir))
      {
        if ($entryname == '.' || $entryname == '..') continue;

        $current_entry = $dir.DIRECTORY_SEPARATOR.$entryname;
        if ((!$this->followLinks) && is_link($current_entry))
        {
          continue;
        }

        if (is_dir($current_entry))
        {
          if ($this->sort === 'type')
          {
            $temp_folders[$entryname] = $current_entry;
          }
          else
          {
            if (($this->type === 'directory' || $this->type === 'any') && ($depth >= $this->mindepth) && !$this->isDiscarded($dir, $entryname) && $this->matchNames($dir, $entryname) && $this->execOk($dir, $entryname))
            {
              $files[] = $current_entry;
            }

            if (!$this->isPruned($dir, $entryname))
            {
              $files = array_merge($files, $this->searchIn($current_entry, $depth + 1));
            }
          }
        }
        else
        {
          if (($this->type !== 'directory' || $this->type === 'any') && ($depth >= $this->mindepth) && !$this->isDiscarded($dir, $entryname) && $this->matchNames($dir, $entryname) && $this->sizeOk($dir, $entryname) && $this->execOk($dir, $entryname))
          {
            if ($this->sort === 'type')
            {
              $temp_files[] = $current_entry;
            }
            else
            {
              $files[] = $current_entry;
            }
          }
        }
      }

      if ($this->sort === 'type')
      {
        ksort($temp_folders);
        foreach($temp_folders as $entryname => $current_entry)
        {
          if (($this->type === 'directory' || $this->type === 'any') && ($depth >= $this->mindepth) && !$this->isDiscarded($dir, $entryname) && $this->matchNames($dir, $entryname) && $this->execOk($dir, $entryname))
          {
            $files[] = $current_entry;
          }

          if (!$this->isPruned($dir, $entryname))
          {
            $files = array_merge($files, $this->searchIn($current_entry, $depth + 1));
          }
        }

        sort($temp_files);
        $files = array_merge($files, $temp_files);
      }

      closedir($current_dir);
    }

    return $files;
  }

  protected function matchNames($dir, $entry)
  {
    if (!count($this->names)) return true;

    // Flags indicating that there was attempts to match
    // at least one "not_name" or "name" rule respectively
    // to following variables:
    $one_not_name_rule = false;
    $one_name_rule = false;

    foreach ($this->names as $args)
    {
      list($not, $regex) = $args;
      $not ? $one_not_name_rule = true : $one_name_rule = true;
      if (preg_match($regex, $entry))
      {
        // We must match ONLY ONE "not_name" or "name" rule:
        // if "not_name" rule matched then we return "false"
        // if "name" rule matched then we return "true"
        return $not ? false : true;
      }
    }

    if ($one_not_name_rule && $one_name_rule)
    {
      return false;
    }
    else if ($one_not_name_rule)
    {
      return true;
    }
    else if ($one_name_rule)
    {
      return false;
    }
    return true;
  }

  protected function sizeOk($dir, $entry)
  {
    if (0 === count($this->sizes)) return true;

    if (!is_file($dir.DIRECTORY_SEPARATOR.$entry)) return true;

    $filesize = filesize($dir.DIRECTORY_SEPARATOR.$entry);
    foreach ($this->sizes as $number_compare)
    {
      if (!$number_compare->test($filesize)) return false;
    }

    return true;
  }

  protected function isPruned($dir, $entry)
  {
    if (0 === count($this->prunes)) return false;

    foreach ($this->prunes as $args)
    {
      $regex = $args[1];
      if (preg_match($regex, $entry)) return true;
    }

    return false;
  }

  protected function isDiscarded($dir, $entry)
  {
    if (0 === count($this->discards)) return false;

    foreach ($this->discards as $args)
    {
      $regex = $args[1];
      if (preg_match($regex, $entry)) return true;
    }

    return false;
  }

  protected function execOk($dir, $entry)
  {
    if (0 === count($this->execs)) return true;

    foreach ($this->execs as $exec)
    {
      if (!call_user_func_array($exec, array($dir, $entry))) return false;
    }

    return true;
  }

  public static function isPathAbsolute($path)
  {
    if ($path{0} === '/' || $path{0} === '\\' ||
        (strlen($path) > 3 && ctype_alpha($path{0}) &&
         $path{1} === ':' &&
         ($path{2} === '\\' || $path{2} === '/')
        )
       )
    {
      return true;
    }

    return false;
  }
}
