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
%Apath: "%sCaster"
  filename: "SplCasterTest.php"
  basename: "SplCasterTest.php"
  pathname: "%sSplCasterTest.php"
  extension: "php"
  realPath: "%sSplCasterTest.php"
  aTime: %s-%s-%d %d:%d:%d
  mTime: %s-%s-%d %d:%d:%d
  cTime: %s-%s-%d %d:%d:%d
  inode: %d
  size: %d
  perms: 0%d
  owner: %d
  group: %d
  type: "file"
  writable: true
  readable: true
  executable: false
  file: true
  dir: false
  link: false
%A}
EOTXT
            ),
            array('https://google.com/about', <<<'EOTXT'
SplFileInfo {
%Apath: "https://google.com"
  filename: "about"
  basename: "about"
  pathname: "https://google.com/about"
  extension: ""
  realPath: false
%A}
EOTXT
            ),
        );
    }

    /** @dataProvider getCastFileInfoTests */
    public function testCastFileInfo($file, $dump)
    {
        $this->assertDumpMatchesFormat($dump, new \SplFileInfo($file));
    }

    public function testCastFileObject()
    {
        $var = new \SplFileObject(__FILE__);
        $var->setFlags(\SplFileObject::DROP_NEW_LINE | \SplFileObject::SKIP_EMPTY);
        $dump = <<<'EOTXT'
SplFileObject {
%Apath: "%sCaster"
  filename: "SplCasterTest.php"
  basename: "SplCasterTest.php"
  pathname: "%sSplCasterTest.php"
  extension: "php"
  realPath: "%sSplCasterTest.php"
  aTime: %s-%s-%d %d:%d:%d
  mTime: %s-%s-%d %d:%d:%d
  cTime: %s-%s-%d %d:%d:%d
  inode: %d
  size: %d
  perms: 0%d
  owner: %d
  group: %d
  type: "file"
  writable: true
  readable: true
  executable: false
  file: true
  dir: false
  link: false
%AcsvControl: array:%d [
    0 => ","
    1 => """
%A]
  flags: DROP_NEW_LINE|SKIP_EMPTY
  maxLineLen: 0
  fstat: array:26 [
    "dev" => %d
    "ino" => %d
    "nlink" => %d
    "rdev" => 0
    "blksize" => %i
    "blocks" => %i
     …20
  ]
  eof: false
  key: 0
}
EOTXT;
        $this->assertDumpMatchesFormat($dump, $var);
    }
}
