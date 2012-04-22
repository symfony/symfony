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
use Symfony\Component\Finder\Adapter;

class FinderTest extends Iterator\RealIteratorTestCase
{
    protected static $tmpDir;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$tmpDir = sys_get_temp_dir().'/symfony2_finder';
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testCreate(Adapter\AdapterInterface $adapter)
    {
        $this->assertInstanceOf('Symfony\Component\Finder\Finder', Finder::create());
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testDirectories(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);
        $this->assertSame($finder, $finder->directories());
        $this->assertIterator($this->toAbsolute(array('foo', 'toto')), $finder->in(self::$tmpDir)->getIterator());

        $finder = Finder::create()->using($adapter);
        $finder->directories();
        $finder->files();
        $finder->directories();
        $this->assertIterator($this->toAbsolute(array('foo', 'toto')), $finder->in(self::$tmpDir)->getIterator());
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testFiles(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);
        $this->assertSame($finder, $finder->files());
        $this->assertIterator($this->toAbsolute(array('foo/bar.tmp', 'test.php', 'test.py')), $finder->in(self::$tmpDir)->getIterator());

        $finder = Finder::create()->using($adapter);
        $finder->files();
        $finder->directories();
        $finder->files();
        $this->assertIterator($this->toAbsolute(array('foo/bar.tmp', 'test.php', 'test.py')), $finder->in(self::$tmpDir)->getIterator());
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testDepth(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);
        $this->assertSame($finder, $finder->depth('< 1'));
        $this->assertIterator($this->toAbsolute(array('foo', 'test.php', 'test.py', 'toto')), $finder->in(self::$tmpDir)->getIterator());

        $finder = Finder::create()->using($adapter);
        $this->assertSame($finder, $finder->depth('<= 0'));
        $this->assertIterator($this->toAbsolute(array('foo', 'test.php', 'test.py', 'toto')), $finder->in(self::$tmpDir)->getIterator());

        $finder = Finder::create()->using($adapter);
        $this->assertSame($finder, $finder->depth('>= 1'));
        $this->assertIterator($this->toAbsolute(array('foo/bar.tmp')), $finder->in(self::$tmpDir)->getIterator());

        $finder = Finder::create()->using($adapter);
        $finder->depth('< 1')->depth('>= 1');
        $this->assertIterator(array(), $finder->in(self::$tmpDir)->getIterator());
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testName(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);
        $this->assertSame($finder, $finder->name('*.php'));
        $this->assertIterator($this->toAbsolute(array('test.php')), $finder->in(self::$tmpDir)->getIterator());

        $finder = Finder::create()->using($adapter);
        $finder->name('test.ph*');
        $finder->name('test.py');
        $this->assertIterator($this->toAbsolute(array('test.php', 'test.py')), $finder->in(self::$tmpDir)->getIterator());
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testNotName(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);
        $this->assertSame($finder, $finder->notName('*.php'));
        $this->assertIterator($this->toAbsolute(array('foo', 'foo/bar.tmp', 'test.py', 'toto')), $finder->in(self::$tmpDir)->getIterator());

        $finder = Finder::create()->using($adapter);
        $finder->notName('*.php');
        $finder->notName('*.py');
        $this->assertIterator($this->toAbsolute(array('foo', 'foo/bar.tmp', 'toto')), $finder->in(self::$tmpDir)->getIterator());

        $finder = Finder::create()->using($adapter);
        $finder->name('test.ph*');
        $finder->name('test.py');
        $finder->notName('*.php');
        $finder->notName('*.py');
        $this->assertIterator(array(), $finder->in(self::$tmpDir)->getIterator());
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testSize(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);
        $this->assertSame($finder, $finder->files()->size('< 1K')->size('> 500'));
        $this->assertIterator($this->toAbsolute(array('test.php')), $finder->in(self::$tmpDir)->getIterator());
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testDate(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);
        $this->assertSame($finder, $finder->files()->date('until last month'));
        $this->assertIterator($this->toAbsolute(array('foo/bar.tmp', 'test.php')), $finder->in(self::$tmpDir)->getIterator());
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testExclude(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);
        $this->assertSame($finder, $finder->exclude('foo'));
        $this->assertIterator($this->toAbsolute(array('test.php', 'test.py', 'toto')), $finder->in(self::$tmpDir)->getIterator());
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testIgnoreVCS(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);
        $this->assertSame($finder, $finder->ignoreVCS(false)->ignoreDotFiles(false));
        $this->assertIterator($this->toAbsolute(array('.git', 'foo', 'foo/bar.tmp', 'test.php', 'test.py', 'toto', '.bar', '.foo', '.foo/.bar')), $finder->in(self::$tmpDir)->getIterator());

        $finder = Finder::create()->using($adapter);
        $finder->ignoreVCS(false)->ignoreVCS(false)->ignoreDotFiles(false);
        $this->assertIterator($this->toAbsolute(array('.git', 'foo', 'foo/bar.tmp', 'test.php', 'test.py', 'toto', '.bar', '.foo', '.foo/.bar')), $finder->in(self::$tmpDir)->getIterator());

        $finder = Finder::create()->using($adapter);
        $this->assertSame($finder, $finder->ignoreVCS(true)->ignoreDotFiles(false));
        $this->assertIterator($this->toAbsolute(array('foo', 'foo/bar.tmp', 'test.php', 'test.py', 'toto', '.bar', '.foo', '.foo/.bar')), $finder->in(self::$tmpDir)->getIterator());
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testIgnoreDotFiles(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);
        $this->assertSame($finder, $finder->ignoreDotFiles(false)->ignoreVCS(false));
        $this->assertIterator($this->toAbsolute(array('.git', '.bar', '.foo', '.foo/.bar', 'foo', 'foo/bar.tmp', 'test.php', 'test.py', 'toto')), $finder->in(self::$tmpDir)->getIterator());

        $finder = new Finder();
        $finder->ignoreDotFiles(false)->ignoreDotFiles(false)->ignoreVCS(false);
        $this->assertIterator($this->toAbsolute(array('.git', '.bar', '.foo', '.foo/.bar', 'foo', 'foo/bar.tmp', 'test.php', 'test.py', 'toto')), $finder->in(self::$tmpDir)->getIterator());

        $finder = Finder::create()->using($adapter);
        $this->assertSame($finder, $finder->ignoreDotFiles(true)->ignoreVCS(false));
        $this->assertIterator($this->toAbsolute(array('foo', 'foo/bar.tmp', 'test.php', 'test.py', 'toto')), $finder->in(self::$tmpDir)->getIterator());

    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testSortByName(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);
        $this->assertSame($finder, $finder->sortByName());
        $this->assertIterator($this->toAbsolute(array('foo', 'foo/bar.tmp', 'test.php', 'test.py', 'toto')), $finder->in(self::$tmpDir)->getIterator());
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testSortByType(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);
        $this->assertSame($finder, $finder->sortByType());
        $this->assertIterator($this->toAbsolute(array('foo', 'toto', 'foo/bar.tmp', 'test.php', 'test.py')), $finder->in(self::$tmpDir)->getIterator());
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testSortByAccessedTime(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);
        $this->assertSame($finder, $finder->sortByAccessedTime());
        $this->assertIterator($this->toAbsolute(array('foo/bar.tmp', 'test.php', 'toto', 'test.py', 'foo')), $finder->in(self::$tmpDir)->getIterator());
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testSortByChangedTime(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);
        $this->assertSame($finder, $finder->sortByChangedTime());
        $this->assertIterator($this->toAbsolute(array('toto', 'test.py', 'test.php', 'foo/bar.tmp', 'foo')), $finder->in(self::$tmpDir)->getIterator());
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testSortByModifiedTime(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);
        $this->assertSame($finder, $finder->sortByModifiedTime());
        $this->assertIterator($this->toAbsolute(array('foo/bar.tmp', 'test.php', 'toto', 'test.py', 'foo')), $finder->in(self::$tmpDir)->getIterator());
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testSort(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);
        $this->assertSame($finder, $finder->sort(function (\SplFileInfo $a, \SplFileInfo $b) { return strcmp($a->getRealpath(), $b->getRealpath()); }));
        $this->assertIterator($this->toAbsolute(array('foo', 'foo/bar.tmp', 'test.php', 'test.py', 'toto')), $finder->in(self::$tmpDir)->getIterator());
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testFilter(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);
        $this->assertSame($finder, $finder->filter(function (\SplFileInfo $f) { return preg_match('/test/', $f) > 0; }));
        $this->assertIterator($this->toAbsolute(array('test.php', 'test.py')), $finder->in(self::$tmpDir)->getIterator());
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testFollowLinks(Adapter\AdapterInterface $adapter)
    {
        if ('\\' == DIRECTORY_SEPARATOR) {
            return;
        }

        $finder = Finder::create()->using($adapter);
        $this->assertSame($finder, $finder->followLinks());
        $this->assertIterator($this->toAbsolute(array('foo', 'foo/bar.tmp', 'test.php', 'test.py', 'toto')), $finder->in(self::$tmpDir)->getIterator());
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testIn(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);
        try {
            $finder->in('foobar');
            $this->fail('->in() throws a \InvalidArgumentException if the directory does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e, '->in() throws a \InvalidArgumentException if the directory does not exist');
        }

        $finder = Finder::create()->using($adapter);
        $iterator = $finder->files()->name('*.php')->depth('< 1')->in(array(self::$tmpDir, __DIR__))->getIterator();

        $this->assertIterator(array(self::$tmpDir.DIRECTORY_SEPARATOR.'test.php', __DIR__.DIRECTORY_SEPARATOR.'FinderTest.php', __DIR__.DIRECTORY_SEPARATOR.'bootstrap.php', __DIR__.DIRECTORY_SEPARATOR.'ExprTest.php'), $iterator);
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testGetIterator(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);
        try {
            $finder->getIterator();
            $this->fail('->getIterator() throws a \LogicException if the in() method has not been called');
        } catch (\Exception $e) {
            $this->assertInstanceOf('LogicException', $e, '->getIterator() throws a \LogicException if the in() method has not been called');
        }

        $finder = Finder::create()->using($adapter);
        $dirs = array();
        foreach ($finder->directories()->in(self::$tmpDir) as $dir) {
            $dirs[] = (string) $dir;
        }

        $expected = $this->toAbsolute(array('foo', 'toto'));

        sort($dirs);
        sort($expected);

        $this->assertEquals($expected, $dirs, 'implements the \IteratorAggregate interface');

        $finder = Finder::create()->using($adapter);
        $this->assertEquals(2, iterator_count($finder->directories()->in(self::$tmpDir)), 'implements the \IteratorAggregate interface');

        $finder = Finder::create()->using($adapter);
        $a = iterator_to_array($finder->directories()->in(self::$tmpDir));
        $a = array_values(array_map(function ($a) { return (string) $a; }, $a));
        sort($a);
        $this->assertEquals($expected, $a, 'implements the \IteratorAggregate interface');
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testRelativePath(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);

        $finder->in(self::$tmpDir);

        $paths = array();

        foreach ($finder as $file) {
            $paths[] = $file->getRelativePath();
        }

        $ref = array("", "", "", "", "foo");

        sort($ref);
        sort($paths);

        $this->assertEquals($ref, $paths);
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testRelativePathname(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);

        $finder->in(self::$tmpDir)->sortByName();

        $paths = array();

        foreach ($finder as $file) {
            $paths[] = $file->getRelativePathname();
        }

        $ref = array("test.php", "toto", "test.py", "foo", "foo".DIRECTORY_SEPARATOR."bar.tmp");

        sort($paths);
        sort($ref);

        $this->assertEquals($ref, $paths);
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testAppendWithAFinder(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);
        $finder->files()->in(self::$tmpDir.DIRECTORY_SEPARATOR.'foo');

        $finder1 = Finder::create()->using($adapter);
        $finder1->directories()->in(self::$tmpDir);

        $finder->append($finder1);

        $this->assertIterator($this->toAbsolute(array('foo', 'foo/bar.tmp', 'toto')), $finder->getIterator());
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testAppendWithAnArray(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);
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
    public function testContains(Adapter\AdapterInterface $adapter, $matchPatterns, $noMatchPatterns, $expected)
    {
        $finder = Finder::create()->using($adapter);
        $finder->in(__DIR__.DIRECTORY_SEPARATOR.'Fixtures')
            ->name('*.txt')->sortByName()
            ->contains($matchPatterns)
            ->notContains($noMatchPatterns);

        $this->assertIterator($this->toAbsoluteFixtures($expected), $finder);
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testContainsOnDirectory(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);
        $finder->in(__DIR__)
            ->directories()
            ->name('Fixtures')
            ->contains('abc');
        $this->assertIterator(array(), $finder);
    }

    /**
     * @dataProvider getAdaptersTestData
     */
    public function testNotContainsOnDirectory(Adapter\AdapterInterface $adapter)
    {
        $finder = Finder::create()->using($adapter);
        $finder->in(__DIR__)
            ->directories()
            ->name('Fixtures')
            ->notContains('abc');
        $this->assertIterator(array(), $finder);
    }

    /**
     * Searching in multiple locations involves AppendIterator which does an unnecessary rewind which leaves FilterIterator
     * with inner FilesystemIterator in an ivalid state.
     *
     * @see https://bugs.php.net/bug.php?id=49104
     */
    public function testMultipleLocations(Adapter\AdapterInterface $adapter)
    {
        $locations = array(
            self::$tmpDir.'/',
            self::$tmpDir.'/toto/',
        );

        // it is expected that there are test.py test.php in the tmpDir
        $finder = new Finder();
        $finder->in($locations)->depth('< 1')->name('test.php');

        $this->assertEquals(1, count($finder));
    }

    public function getAdaptersTestData()
    {
        return array_map(function (Adapter\AdapterInterface $adapter)  {
            return array($adapter);
        }, $this->getValidAdapters());
    }

    public function getContainsTestData()
    {
        $tests = array(
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

        $data = array();
        foreach ($this->getValidAdapters() as $adapter) {
            foreach ($tests as $test) {
                $data[] = array_merge(array($adapter), $test);
            }
        }

        return $data;
    }

    private function getValidAdapters()
    {
        return array_filter(
            array(new Adapter\GnuFindAdapter(), new Adapter\PhpAdapter()),
            function (Adapter\AdapterInterface $adapter)  { return $adapter->isValid(); }
        );
    }
}
