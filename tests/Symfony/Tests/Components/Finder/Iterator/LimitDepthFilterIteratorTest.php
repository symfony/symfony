<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Finder\Iterator;

use Symfony\Components\Finder\Iterator\LimitDepthFilterIterator;

require_once __DIR__.'/RealIteratorTestCase.php';

class LimitDepthFilterIteratorTest extends RealIteratorTestCase
{
  /**
   * @dataProvider getAcceptData
   */
  public function testAccept($baseDir, $minDepth, $maxDepth, $expected)
  {
    $inner = new Iterator(self::$files);

    $iterator = new LimitDepthFilterIterator($inner, $baseDir, $minDepth, $maxDepth);

    $this->assertIterator($expected, $iterator);
  }

  public function getAcceptData()
  {
    return array(
      array(sys_get_temp_dir().'/symfony2_finder', 0, INF, array(sys_get_temp_dir().'/symfony2_finder/.git', sys_get_temp_dir().'/symfony2_finder/test.py', sys_get_temp_dir().'/symfony2_finder/foo', sys_get_temp_dir().'/symfony2_finder/foo/bar.tmp', sys_get_temp_dir().'/symfony2_finder/test.php', sys_get_temp_dir().'/symfony2_finder/toto')),
      array(sys_get_temp_dir().'/symfony2_finder', 0, 0, array(sys_get_temp_dir().'/symfony2_finder/.git', sys_get_temp_dir().'/symfony2_finder/test.py', sys_get_temp_dir().'/symfony2_finder/foo', sys_get_temp_dir().'/symfony2_finder/test.php', sys_get_temp_dir().'/symfony2_finder/toto')),
      array(sys_get_temp_dir().'/symfony2_finder', 1, 1, array(sys_get_temp_dir().'/symfony2_finder/foo/bar.tmp')),
    );
  }
}
