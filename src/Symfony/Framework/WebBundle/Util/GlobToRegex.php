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
 * Match globbing patterns against text.
 *
 *   if match_glob("foo.*", "foo.bar") echo "matched\n";
 *
 * // prints foo.bar and foo.baz
 * $regex = glob_to_regex("foo.*");
 * for (array('foo.bar', 'foo.baz', 'foo', 'bar') as $t)
 * {
 *   if (/$regex/) echo "matched: $car\n";
 * }
 *
 * GlobToRegex implements glob(3) style matching that can be used to match
 * against text, rather than fetching names from a filesystem.
 *
 * based on perl Text::Glob module.
 *
 * @package    symfony
 * @author     Fabien Potencier <fabien.potencier@gmail.com> php port
 * @author     Richard Clamp <richardc@unixbeard.net> perl version
 * @copyright  2004-2005 Fabien Potencier <fabien.potencier@gmail.com>
 * @copyright  2002 Richard Clamp <richardc@unixbeard.net>
 */
class GlobToRegex
{
  protected static $strict_leading_dot = true;
  protected static $strict_wildcard_slash = true;

  public static function setStrictLeadingDot($boolean)
  {
    self::$strict_leading_dot = $boolean;
  }

  public static function setStrictWildcardSlash($boolean)
  {
    self::$strict_wildcard_slash = $boolean;
  }

  /**
   * Returns a compiled regex which is the equiavlent of the globbing pattern.
   *
   * @param  string $glob  pattern
   * @return string regex
   */
  public static function glob_to_regex($glob)
  {
    $first_byte = true;
    $escaping = false;
    $in_curlies = 0;
    $regex = '';
    $sizeGlob = strlen($glob);
    for ($i = 0; $i < $sizeGlob; $i++)
    {
      $car = $glob[$i];
      if ($first_byte)
      {
        if (self::$strict_leading_dot && $car !== '.')
        {
          $regex .= '(?=[^\.])';
        }

        $first_byte = false;
      }

      if ($car === '/')
      {
        $first_byte = true;
      }

      if ($car === '.' || $car === '(' || $car === ')' || $car === '|' || $car === '+' || $car === '^' || $car === '$')
      {
        $regex .= "\\$car";
      }
      elseif ($car === '*')
      {
        $regex .= ($escaping ? '\\*' : (self::$strict_wildcard_slash ? '[^/]*' : '.*'));
      }
      elseif ($car === '?')
      {
        $regex .= ($escaping ? '\\?' : (self::$strict_wildcard_slash ? '[^/]' : '.'));
      }
      elseif ($car === '{')
      {
        $regex .= ($escaping ? '\\{' : '(');
        if (!$escaping) ++$in_curlies;
      }
      elseif ($car === '}' && $in_curlies)
      {
        $regex .= ($escaping ? '}' : ')');
        if (!$escaping) --$in_curlies;
      }
      elseif ($car === ',' && $in_curlies)
      {
        $regex .= ($escaping ? ',' : '|');
      }
      elseif ($car === '\\')
      {
        if ($escaping)
        {
          $regex .= '\\\\';
          $escaping = false;
        }
        else
        {
          $escaping = true;
        }

        continue;
      }
      else
      {
        $regex .= $car;
      }
      $escaping = false;
    }

    return '#^'.$regex.'$#';
  }
}
