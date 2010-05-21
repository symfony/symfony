<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Finder;

use Symfony\Components\Finder\Finder;

require_once __DIR__.'/Iterator/RealIteratorTestCase.php';

class FinderTest extends Iterator\RealIteratorTestCase
{
    static protected $tmpDir;

    static public function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$tmpDir = sys_get_temp_dir().'/symfony2_finder/';
    }

    public function testDirectories()
    {
        $finder = new Finder();
        $this->assertSame($finder, $finder->directories());
        $this->assertIterator($this->toAbsolute(array('foo', 'toto')), $finder->in(self::$tmpDir)->getIterator());

        $finder = new Finder();
        $finder->directories();
        $finder->files();
        $finder->directories();
        $this->assertIterator($this->toAbsolute(array('foo', 'toto')), $finder->in(self::$tmpDir)->getIterator());
    }

    public function testFiles()
    {
        $finder = new Finder();
        $this->assertSame($finder, $finder->files());
        $this->assertIterator($this->toAbsolute(array('foo/bar.tmp', 'test.php', 'test.py')), $finder->in(self::$tmpDir)->getIterator());

        $finder = new Finder();
        $finder->files();
        $finder->directories();
        $finder->files();
        $this->assertIterator($this->toAbsolute(array('foo/bar.tmp', 'test.php', 'test.py')), $finder->in(self::$tmpDir)->getIterator());
    }

    public function testDepth()
    {
        $finder = new Finder();
        $this->assertSame($finder, $finder->depth('< 1'));
        $this->assertIterator($this->toAbsolute(array('foo', 'test.php', 'test.py', 'toto')), $finder->in(self::$tmpDir)->getIterator());

        $finder = new Finder();
        $this->assertSame($finder, $finder->depth('<= 0'));
        $this->assertIterator($this->toAbsolute(array('foo', 'test.php', 'test.py', 'toto')), $finder->in(self::$tmpDir)->getIterator());

        $finder = new Finder();
        $this->assertSame($finder, $finder->depth('>= 1'));
        $this->assertIterator($this->toAbsolute(array('foo/bar.tmp')), $finder->in(self::$tmpDir)->getIterator());

        $finder = new Finder();
        $finder->depth('< 1')->depth('>= 1');
        $this->assertIterator(array(), $finder->in(self::$tmpDir)->getIterator());
    }

    public function testName()
    {
        $finder = new Finder();
        $this->assertSame($finder, $finder->name('*.php'));
        $this->assertIterator($this->toAbsolute(array('test.php')), $finder->in(self::$tmpDir)->getIterator());

        $finder = new Finder();
        $finder->name('test.ph*');
        $finder->name('test.py');
        $this->assertIterator($this->toAbsolute(array('test.php', 'test.py')), $finder->in(self::$tmpDir)->getIterator());
    }

    public function testNotName()
    {
        $finder = new Finder();
        $this->assertSame($finder, $finder->notName('*.php'));
        $this->assertIterator($this->toAbsolute(array('foo', 'foo/bar.tmp', 'test.py', 'toto')), $finder->in(self::$tmpDir)->getIterator());

        $finder = new Finder();
        $finder->notName('*.php');
        $finder->notName('*.py');
        $this->assertIterator($this->toAbsolute(array('foo', 'foo/bar.tmp', 'toto')), $finder->in(self::$tmpDir)->getIterator());

        $finder = new Finder();
        $finder->name('test.ph*');
        $finder->name('test.py');
        $finder->notName('*.php');
        $finder->notName('*.py');
        $this->assertIterator(array(), $finder->in(self::$tmpDir)->getIterator());
    }

    public function testSize()
    {
        $finder = new Finder();
        $this->assertSame($finder, $finder->files()->size('< 1K')->size('> 500'));
        $this->assertIterator($this->toAbsolute(array('test.php')), $finder->in(self::$tmpDir)->getIterator());
    }

    public function testDate()
    {
        $finder = new Finder();
        $this->assertSame($finder, $finder->files()->date('until last month'));
        $this->assertIterator($this->toAbsolute(array('foo/bar.tmp', 'test.php')), $finder->in(self::$tmpDir)->getIterator());
    }

    public function testExclude()
    {
        $finder = new Finder();
        $this->assertSame($finder, $finder->exclude('foo'));
        $this->assertIterator($this->toAbsolute(array('test.php', 'test.py', 'toto')), $finder->in(self::$tmpDir)->getIterator());
    }

    public function testIgnoreVCS()
    {
        $finder = new Finder();
        $this->assertSame($finder, $finder->ignoreVCS(false));
        $this->assertIterator($this->toAbsolute(array('.git', 'foo', 'foo/bar.tmp', 'test.php', 'test.py', 'toto')), $finder->in(self::$tmpDir)->getIterator());

        $finder = new Finder();
        $this->assertSame($finder, $finder->ignoreVCS(true));
        $this->assertIterator($this->toAbsolute(array('foo', 'foo/bar.tmp', 'test.php', 'test.py', 'toto')), $finder->in(self::$tmpDir)->getIterator());
    }

    public function testSortByName()
    {
        $finder = new Finder();
        $this->assertSame($finder, $finder->sortByName());
        $this->assertIterator($this->toAbsolute(array('foo', 'foo/bar.tmp', 'test.php', 'test.py', 'toto')), $finder->in(self::$tmpDir)->getIterator());
    }

    public function testSortByType()
    {
        $finder = new Finder();
        $this->assertSame($finder, $finder->sortByType());
        $this->assertIterator($this->toAbsolute(array('foo', 'toto', 'foo/bar.tmp', 'test.php', 'test.py')), $finder->in(self::$tmpDir)->getIterator());
    }

    public function testSort()
    {
        $finder = new Finder();
        $this->assertSame($finder, $finder->sort(function (\SplFileInfo $a, \SplFileInfo $b) { return strcmp($a->getRealpath(), $b->getRealpath()); }));
        $this->assertIterator($this->toAbsolute(array('foo', 'foo/bar.tmp', 'test.php', 'test.py', 'toto')), $finder->in(self::$tmpDir)->getIterator());
    }

    public function testFilter()
    {
        $finder = new Finder();
        $this->assertSame($finder, $finder->filter(function (\SplFileInfo $f) { return preg_match('/test/', $f) > 0; }));
        $this->assertIterator($this->toAbsolute(array('test.php', 'test.py')), $finder->in(self::$tmpDir)->getIterator());
    }

    public function testFollowLinks()
    {
        if ('\\' == DIRECTORY_SEPARATOR) {
            return;
        }

        $finder = new Finder();
        $this->assertSame($finder, $finder->followLinks());
        $this->assertIterator($this->toAbsolute(array('foo', 'foo/bar.tmp', 'test.php', 'test.py', 'toto')), $finder->in(self::$tmpDir)->getIterator());
    }

    public function testIn()
    {
        $finder = new Finder();
        try {
            $finder->in('foobar');
            $this->fail('->in() throws a \InvalidArgumentException if the directory does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e, '->in() throws a \InvalidArgumentException if the directory does not exist');
        }

        $finder = new Finder();
        $iterator = $finder->files()->name('*.php')->depth('< 1')->in(array(self::$tmpDir, __DIR__))->getIterator();

        $this->assertIterator(array(self::$tmpDir.'test.php', __DIR__.'/FinderTest.php', __DIR__.'/GlobTest.php'), $iterator);
    }

    public function testGetIterator()
    {
        $finder = new Finder();
        try {
            $finder->getIterator();
            $this->fail('->getIterator() throws a \LogicException if the in() method has not been called');
        } catch (\Exception $e) {
            $this->assertInstanceOf('LogicException', $e, '->getIterator() throws a \LogicException if the in() method has not been called');
        }

        $finder = new Finder();
        $dirs = array();
        foreach ($finder->directories()->in(self::$tmpDir) as $dir) {
            $dirs[] = (string) $dir;
        }

        $this->assertEquals($this->toAbsolute(array('foo', 'toto')), $dirs, 'implements the \IteratorAggregate interface');

        $finder = new Finder();
        $this->assertEquals(2, iterator_count($finder->directories()->in(self::$tmpDir)), 'implements the \IteratorAggregate interface');

        $finder = new Finder();
        $a = iterator_to_array($finder->directories()->in(self::$tmpDir));
        $this->assertEquals($this->toAbsolute(array('foo', 'toto')), array_values(array_map(function ($a) { return (string) $a; }, $a)), 'implements the \IteratorAggregate interface');
    }

    protected function toAbsolute($files)
    {
        $f = array();
        foreach ($files as $file) {
            $f[] = self::$tmpDir.$file;
        }

        return $f;
    }
}
