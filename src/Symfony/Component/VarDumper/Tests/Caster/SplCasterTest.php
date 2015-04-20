<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Caster;

use Symfony\Component\VarDumper\Test\VarDumperTestCase;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class SplCasterTest extends VarDumperTestCase
{
    public function getCastFileInfoTests()
    {
        return array(
            array(__FILE__, <<<'EOTXT'
SplFileInfo {
  path: "%s/src/Symfony/Component/VarDumper/Tests/Caster"
  filename: "SplCasterTest.php"
  basename: "SplCasterTest.php"
  pathname: "%s/src/Symfony/Component/VarDumper/Tests/Caster/SplCasterTest.php"
  extension: "php"
  realPath: "%s/src/Symfony/Component/VarDumper/Tests/Caster/SplCasterTest.php"
  aTime: %s-%s-%d %d:%d:%d
  mTime: %s-%s-%d %d:%d:%d
  cTime: %s-%s-%d %d:%d:%d
  inode: %d
  size: %d
  perms: 0100644
  owner: 1000
  group: 1000
  type: "file"
  writable: true
  readable: true
  executable: false
  file: true
  dir: false
  link: false
}
EOTXT
            ),
            array('https://google.com/about', <<<'EOTXT'
SplFileInfo {
  path: "https://google.com"
  filename: "about"
  basename: "about"
  pathname: "https://google.com/about"
  extension: ""
  realPath: false
  writable: false
  readable: false
  executable: false
  file: false
  dir: false
  link: false
}
EOTXT
            ),
        );
    }

    /** @dataProvider getCastFileInfoTests */
    public function testCastFileInfo($file, $dump)
    {
        $this->assertDumpMatchesFormat($dump, new \SplFileInfo($file));
    }
}
