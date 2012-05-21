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

use Symfony\Component\Finder\Finder;

class FinderTest extends Iterator\RealIteratorTestCase
{
    static protected $tmpDir;

    static public function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$tmpDir = sys_get_temp_dir().'/symfony2_finder';
    }

    public function testCreate()
    {
        $this->assertInstanceOf('Symfony\Component\Finder\Finder', Finder::create());
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
        $this->assertSame($finder, $finder->ignoreVCS(false)->ignoreDotFiles(false));
        $this->assertIterator($this->toAbsolute(array('.git', 'foo', 'foo/bar.tmp', 'test.php', 'test.py', 'toto', '.bar', '.foo', '.foo/.bar')), $finder->in(self::$tmpDir)->getIterator());

        $finder = new Finder();
        $finder->ignoreVCS(false)->ignoreVCS(false)->ignoreDotFiles(false);
        $this->assertIterator($this->toAbsolute(array('.git', 'foo', 'foo/bar.tmp', 'test.php', 'test.py', 'toto', '.bar', '.foo', '.foo/.bar')), $finder->in(self::$tmpDir)->getIterator());

        $finder = new Finder();
        $this->assertSame($finder, $finder->ignoreVCS(true)->ignoreDotFiles(false));
        $this->assertIterator($this->toAbsolute(array('foo', 'foo/bar.tmp', 'test.php', 'test.py', 'toto', '.bar', '.foo', '.foo/.bar')), $finder->in(self::$tmpDir)->getIterator());

    }

    public function testIgnoreDotFiles()
    {
        $finder = new Finder();
        $this->assertSame($finder, $finder->ignoreDotFiles(false)->ignoreVCS(false));
        $this->assertIterator($this->toAbsolute(array('.git', '.bar', '.foo', '.foo/.bar', 'foo', 'foo/bar.tmp', 'test.php', 'test.py', 'toto')), $finder->in(self::$tmpDir)->getIterator());

        $finder = new Finder();
        $finder->ignoreDotFiles(false)->ignoreDotFiles(false)->ignoreVCS(false);
        $this->assertIterator($this->toAbsolute(array('.git', '.bar', '.foo', '.foo/.bar', 'foo', 'foo/bar.tmp', 'test.php', 'test.py', 'toto')), $finder->in(self::$tmpDir)->getIterator());

        $finder = new Finder();
        $this->assertSame($finder, $finder->ignoreDotFiles(true)->ignoreVCS(false));
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


    public function testSortByAccessedTime()
    {
        $finder = new Finder();
        $this->assertSame($finder, $finder->sortByAccessedTime());
        $this->assertIterator($this->toAbsolute(array('foo/bar.tmp', 'test.php', 'toto', 'test.py', 'foo')), $finder->in(self::$tmpDir)->getIterator());
    }

    public function testSortByChangedTime()
    {
        $finder = new Finder();
        $this->assertSame($finder, $finder->sortByChangedTime());
        $this->assertIterator($this->toAbsolute(array('toto', 'test.py', 'test.php', 'foo/bar.tmp', 'foo')), $finder->in(self::$tmpDir)->getIterator());
    }

    public function testSortByModifiedTime()
    {
        $finder = new Finder();
        $this->assertSame($finder, $finder->sortByModifiedTime());
        $this->assertIterator($this->toAbsolute(array('foo/bar.tmp', 'test.php', 'toto', 'test.py', 'foo')), $finder->in(self::$tmpDir)->getIterator());
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

        $this->assertIterator(array(self::$tmpDir.DIRECTORY_SEPARATOR.'test.php', __DIR__.DIRECTORY_SEPARATOR.'FinderTest.php', __DIR__.DIRECTORY_SEPARATOR.'bootstrap.php', __DIR__.DIRECTORY_SEPARATOR.'GlobTest.php'), $iterator);
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

        $expected = $this->toAbsolute(array('foo', 'toto'));

        sort($dirs);
        sort($expected);

        $this->assertEquals($expected, $dirs, 'implements the \IteratorAggregate interface');

        $finder = new Finder();
        $this->assertEquals(2, iterator_count($finder->directories()->in(self::$tmpDir)), 'implements the \IteratorAggregate interface');

        $finder = new Finder();
        $a = iterator_to_array($finder->directories()->in(self::$tmpDir));
        $a = array_values(array_map(function ($a) { return (string) $a; }, $a));
        sort($a);
        $this->assertEquals($expected, $a, 'implements the \IteratorAggregate interface');
    }

    public function testRelativePath()
    {
        $finder = new Finder();

        $finder->in(self::$tmpDir);

        $paths = array();

        foreach ($finder as $file) {
            $paths[] = $file->getRelativePath();
        }

        $ref = array("", "", "", "", "foo");

        sort($ref);
        sort($paths);

        $this->assertEquals($paths, $ref);
    }

    public function testRelativePathname()
    {
        $finder = new Finder();

        $finder->in(self::$tmpDir)->sortByName();

        $paths = array();

        foreach ($finder as $file) {
            $paths[] = $file->getRelativePathname();
        }

        $ref = array("test.php", "toto", "test.py", "foo", "foo".DIRECTORY_SEPARATOR."bar.tmp");

        sort($paths);
        sort($ref);

        $this->assertEquals($paths, $ref);
    }

    public function testAppendWithAFinder()
    {
        $finder = new Finder();
        $finder->files()->in(self::$tmpDir.DIRECTORY_SEPARATOR.'foo');

        $finder1 = new Finder();
        $finder1->directories()->in(self::$tmpDir);

        $finder->append($finder1);

        $this->assertIterator($this->toAbsolute(array('foo', 'foo/bar.tmp', 'toto')), $finder->getIterator());
    }

    public function testAppendWithAnArray()
    {
        $finder = new Finder();
        $finder->files()->in(self::$tmpDir.DIRECTORY_SEPARATOR.'foo');

        $finder->append($this->toAbsolute(array('foo', 'toto')));

        $this->assertIterator($this->toAbsolute(array('foo', 'foo/bar.tmp', 'toto')), $finder->getIterator());
    }

    public function testCountDirectories()
    {
        $finder = new Finder();
        $directory = $finder->directories()->in(self::$tmpDir);
        $i = 0;

        foreach ($directory as $dir) {
            $i++;
        }

        $this->assertCount($i, $directory);
    }

    public function testCountFiles()
    {
        $finder = new Finder();
        $files = $finder->files()->in(__DIR__.DIRECTORY_SEPARATOR.'Fixtures');
        $i = 0;

        foreach ($files as $file) {
            $i++;
        }

        $this->assertCount($i, $files);
    }

    public function testCountWithoutIn()
    {
        $finder = new Finder();
        $finder->files();

        try {
            count($finder);
            $this->fail('Countable makes use of the getIterator command');
        } catch (\Exception $e) {
            $this->assertInstanceOf('LogicException', $e, '->getIterator() throws \LogicException when no logic has been entered');
        }
    }

    protected function toAbsolute($files)
    {
        $f = array();
        foreach ($files as $file) {
            $f[] = self::$tmpDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
        }

        return $f;
    }

    protected function toAbsoluteFixtures($files)
    {
        $f = array();
        foreach ($files as $file) {
            $f[] = __DIR__.DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.$file;
        }

        return $f;
    }

    /**
     * @dataProvider getContainsTestData
     */
    public function testContains($matchPatterns, $noMatchPatterns, $expected)
    {
        $finder = new Finder();
        $finder->in(__DIR__.DIRECTORY_SEPARATOR.'Fixtures')
            ->name('*.txt')->sortByName()
            ->contains($matchPatterns)
            ->notContains($noMatchPatterns);

        $this->assertIterator($this->toAbsoluteFixtures($expected), $finder);
    }

    public function getContainsTestData()
    {
        return array(
            array('', '', array()),
            array('foo', 'bar', array()),
            array('', 'foobar', array('dolor.txt', 'ipsum.txt', 'lorem.txt')),
            array('lorem ipsum dolor sit amet', 'foobar', array('lorem.txt')),
            array('sit', 'bar', array('dolor.txt', 'ipsum.txt', 'lorem.txt')),
            array('dolor sit amet', '@^L@m', array('dolor.txt', 'ipsum.txt')),
            array('/^lorem ipsum dolor sit amet$/m', 'foobar', array('lorem.txt')),
            array('lorem', 'foobar', array('lorem.txt')),

            array('', 'lorem', array('dolor.txt', 'ipsum.txt')),
            array('ipsum dolor sit amet', '/^IPSUM/m', array('lorem.txt')),
        );
    }

    public function testContainsOnDirectory()
    {
        $finder = new Finder();
        $finder->in(__DIR__)
            ->directories()
            ->name('Fixtures')
            ->contains('abc');
        $this->assertIterator(array(), $finder);
    }

    public function testNotContainsOnDirectory()
    {
        $finder = new Finder();
        $finder->in(__DIR__)
            ->directories()
            ->name('Fixtures')
            ->notContains('abc');
        $this->assertIterator(array(), $finder);
    }

}
