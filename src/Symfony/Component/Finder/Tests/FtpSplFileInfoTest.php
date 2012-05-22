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
            array('',            FtpSplFileInfo::TYPE_UNKNOWN),
            array(123,           FtpSplFileInfo::TYPE_UNKNOWN),
        );
    }

    /**
      *
      * @dataProvider getTestConstructorData
      */
    public function testConstructor($data, $expected)
    {
        $f = new FtpSplFileInfo($data['file'], $data['relativePath'], $data['relativePathname'], $data['basePath']);
        $result = array(
            'baseneme'         => $f->getBasename(),
            'filename'         => $f->getFilename(),
            'path'             => $f->getPath(),
            'pathname'         => $f->getPathname(),
            'realPath'         => $f->getRealPath(),
            'relativepath'     => $f->getRelativePath(),
            'relativePathname' => $f->getRelativePathname(),
            'itemName'         => $f->getItemname($data['item']),
        );
        $this->assertEquals($expected, $result);
    }

    public function getTestConstructorData()
    {
        return array(

            array(
                'data'   => array(
                    'file'             => '/',
                    'relativePath'     => '',
                    'relativePathname' => '',
                    'basePath'         => '',
                    'item'             => 'pub',
                ),
                'result' => array(
                    'baseneme'         => '',
                    'filename'         => '/',
                    'path'             => '',
                    'pathname'         => '/',
                    'realPath'         => '/',
                    'relativepath'     => '',
                    'relativePathname' => '',
                    'itemName'         => '/pub',
                )
            ),

            array(
                'data'   => array(
                    'file'             => '/pub',
                    'relativePath'     => '',
                    'relativePathname' => '',
                    'basePath'         => '',
                    'item'             => 'file.txt',
                ),
                'result' => array(
                    'baseneme'         => 'pub',
                    'filename'         => '/pub',
                    'path'             => '',
                    'pathname'         => '/pub',
                    'realPath'         => '/pub',
                    'relativepath'     => '',
                    'relativePathname' => '',
                    'itemName'         => '/pub/file.txt',
                )
            ),
            array(
                'data'   => array(
                    'file'             => '/tmp/my/dir/',
                    'relativePath'     => '',
                    'relativePathname' => '',
                    'basePath'         => '',
                    'item'             => 'nextFile.yml',
                ),
                'result' => array(
                    'baseneme'         => 'dir',
                    'filename'         => 'dir',
                    'path'             => '/tmp/my',
                    'pathname'         => '/tmp/my/dir',
                    'realPath'         => '/tmp/my/dir',
                    'relativepath'     => '',
                    'relativePathname' => '',
                    'itemName'         => '/tmp/my/dir/nextFile.yml',
                )
            ),
        );
    }


    /**
      *
      * @dataProvider getTestGetItemnameData
      */
    public function testGetItemname($directory, $item, $result)
    {
        $ftp = new FtpSplFileInfo($directory, '', '', '');
        $this->assertEquals($result, $ftp->getItemname($item));
    }

    public function getTestGetItemnameData()
    {
        return array(
            array('/', 'file', '/file'),
            array('/pub', 'file', '/pub/file'),
            array('/lorem/ipsum/dolor', 'new', '/lorem/ipsum/dolor/new'),
        );
    }


}
