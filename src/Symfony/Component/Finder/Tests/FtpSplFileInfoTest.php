<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Tests;

use Symfony\Component\Finder\FtpSplFileInfo;

class FtpSplFileInfoTest extends Iterator\RealIteratorTestCase
{
    /**
      *
      * @dataProvider getTestParseRawListItemData
      */
    public function testParseRawListItem($string, $type)
    {
        $this->assertEquals($type, FtpSplFileInfo::parseRawListItem($string));
    }

    public function getTestParseRawListItemData()
    {
        return array(
            array('d   ',        FtpSplFileInfo::TYPE_DIRECTORY),
            array('d',           FtpSplFileInfo::TYPE_DIRECTORY),
            array('drwxrwxrwx',  FtpSplFileInfo::TYPE_DIRECTORY),
            array('-',           FtpSplFileInfo::TYPE_FILE),
            array('-rwxrwxrwx',  FtpSplFileInfo::TYPE_FILE),
            array('a',           FtpSplFileInfo::TYPE_UNKNOWN),
            array('?fdsaf',      FtpSplFileInfo::TYPE_UNKNOWN),
            array('link',        FtpSplFileInfo::TYPE_LINK),
        );
    }

    /**
      *
      * @dataProvider getTestParseDirectoryData
      */
    public function testParseDirectory($file, $array)
    {
        $this->assertEquals($array, FtpSplFileInfo::parseFile($file));
    }

    public function getTestParseDirectoryData()
    {
        return array(
            array('/file',             array('filename' => 'file', 'path' => '/')),
            array('/some/other/file',  array('filename' => 'file', 'path' => '/some/other')),
        );
    }

    /**
      *
      * @dataProvider getTestConstructorOfDirectoryData
      */
    public function testConstructorOfDirectory($directory, $filename, $path, $fullfilename)
    {
        $f = new FtpSplFileInfo($directory);
        $this->assertTrue($f->isDir());
        $this->assertFalse($f->isFile());
        $this->assertEquals($filename,      $f->getFilename());
        $this->assertEquals($path,          $f->getPath());
        $this->assertEquals($fullfilename,  $f->getFullFilename());
    }

    public function getTestConstructorOfDirectoryData()
    {
        return array(
            array('/', '.', '/', '/'),
            array('/pub', '.', '/pub', '/pub'),
            array('/lorem/ipsum/dolor', '.', '/lorem/ipsum/dolor', '/lorem/ipsum/dolor'),
        );
    }

    /**
      *
      * @dataProvider getTestConstructorOfFileData
      */
    public function testConstructorOfFile($param, $filename, $path, $fullfilename)
    {
        $f = new FtpSplFileInfo($param, FtpSplFileInfo::TYPE_FILE);
        $this->assertTrue($f->isFile());
        $this->assertFalse($f->isDir());
        $this->assertEquals($filename,      $f->getFilename());
        $this->assertEquals($path,          $f->getPath());
        $this->assertEquals($fullfilename,  $f->getFullFilename());

    }

    public function getTestConstructorOfFileData()
    {
        return array(
            array('/readme.txt', 'readme.txt', '/', '/readme.txt'),
            array('/some/other/dir/f.txt', 'f.txt', '/some/other/dir', '/some/other/dir/f.txt'),
        );
    }

    /**
      *
      * @dataProvider getTestGetItemnameData
      */
    public function testGetItemname($directory, $item, $result)
    {
        $ftp = new FtpSplFileInfo($directory);
        $this->assertEquals($result, $ftp->getItemname($item));
    }

    public function getTestGetItemnameData()
    {
        return array(
            array('/', 'file', '/file'),
            array('/pub', 'file', '/pub/file'),
            array('/lorem/ipsum/dolor', 'new', '/lorem/ipsum/dolor/new'),
            array('/this/is/a', 'file.txt', '/this/is/a/file.txt'),
        );
    }

}
