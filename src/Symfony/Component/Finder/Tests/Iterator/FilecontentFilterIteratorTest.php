<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Tests\Iterator;

use Symfony\Component\Finder\Iterator\FilecontentFilterIterator;

class FilecontentFilterIteratorTest extends IteratorTestCase
{

    public function testAccept()
    {
        $inner = new MockFileListIterator(array('test.txt'));
        $iterator = new FilecontentFilterIterator($inner, array(), array());
        $this->assertIterator(array('test.txt'), $iterator);
    }

    public function testDirectory()
    {
        $inner = new MockFileListIterator(array('directory'));
        $iterator = new FilecontentFilterIterator($inner, array('directory'), array());
        $this->assertIterator(array(), $iterator);
    }

    public function testUnreadableFile()
    {
        $inner = new MockFileListIterator(array('file r-'));
        $iterator = new FilecontentFilterIterator($inner, array('file r-'), array());
        $this->assertIterator(array(), $iterator);
    }

    /**
     * @dataProvider getTestFilterData
     */
    public function testFilter(\Iterator $inner, array $matchPatterns, array $noMatchPatterns, array $resultArray)
    {
        $iterator = new FilecontentFilterIterator($inner, $matchPatterns, $noMatchPatterns);
        $this->assertIterator($resultArray, $iterator);
    }

    public function getTestFilterData()
    {
        $inner = new MockFileListIterator();

        $inner[] = new MockSplFileInfo(array(
            'name'     => 'a.txt',
            'contents' => 'Lorem ipsum...',
            'type'     => 'file',
            'mode'     => 'r+')
        );

        $inner[] = new MockSplFileInfo(array(
            'name'     => 'b.yml',
            'contents' => 'dolor sit...',
            'type'     => 'file',
            'mode'     => 'r+')
        );

        $inner[] = new MockSplFileInfo(array(
            'name'     => 'some/other/dir/third.php',
            'contents' => 'amet...',
            'type'     => 'file',
            'mode'     => 'r+')
        );

        $inner[] = new MockSplFileInfo(array(
            'name'     => 'unreadable-file.txt',
            'contents' => false,
            'type'     => 'file',
            'mode'     => 'r+')
        );

        return array(
            array($inner, array('.'), array(), array('a.txt', 'b.yml', 'some/other/dir/third.php')),
            array($inner, array('ipsum'), array(), array('a.txt')),
            array($inner, array('i', 'amet'), array('Lorem', 'amet'), array('b.yml')),
        );
    }
}

class MockSplFileInfo extends \SplFileInfo
{
    const   TYPE_DIRECTORY = 1;
    const   TYPE_FILE      = 2;
    const   TYPE_UNKNOWN   = 3;

    private $contents = null;
    private $mode     = null;
    private $type     = null;

    public function __construct($param)
    {
        if (is_string($param)) {
            parent::__construct($param);
        } elseif (is_array($param)) {

            $defaults = array(
              'name'     => 'file.txt',
              'contents' => null,
              'mode'     => null,
              'type'     => null
            );
            $defaults = array_merge($defaults, $param);
            parent::__construct($defaults['name']);
            $this->setContents($defaults['contents']);
            $this->setMode($defaults['mode']);
            $this->setType($defaults['type']);
        } else {
            throw new \RuntimeException(sprintf('Incorrect parameter "%s"', $param));
        }
    }

    public function isFile()
    {
        if ($this->type === null) {
            return preg_match('/file/', $this->getFilename());
        };

        return self::TYPE_FILE === $this->type;
    }

    public function isDir()
    {
        if ($this->type === null) {
            return preg_match('/directory/', $this->getFilename());
        }

        return self::TYPE_DIRECTORY === $this->type;
    }

    public function isReadable()
    {
        if ($this->mode === null) {
            return preg_match('/r\+/', $this->getFilename());
        }

        return preg_match('/r\+/', $this->mode);
    }

    public function getContents()
    {
        return $this->contents;
    }

    public function setContents($contents)
    {
        $this->contents = $contents;
    }

    public function setMode($mode)
    {
        $this->mode = $mode;
    }

    public function setType($type)
    {
        if (is_string($type)) {
            switch ($type) {
                case 'directory':
                case 'd':
                    $this->type = self::TYPE_DIRECTORY;
                    break;
                case 'file':
                case 'f':
                    $this->type = self::TYPE_FILE;
                    break;
                default:
                    $this->type = self::TYPE_UNKNOWN;
            }
        } else {
            $this->type = $type;
        }
    }
}

class MockFileListIterator extends \ArrayIterator
{
    public function __construct(array $filesArray = array())
    {
        $files = array_map(function($file){ return new MockSplFileInfo($file); }, $filesArray);
        parent::__construct($files);
    }
}
