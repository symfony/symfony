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

abstract class IteratorTestCase extends \PHPUnit_Framework_TestCase
{
    protected function assertIterator($expected, \Traversable $iterator)
    {
        // set iterator_to_array $use_key to false to avoid values merge
        // this made FinderTest::testAppendWithAnArray() failed with GnuFinderAdapter
        $values = array_map(function (\SplFileInfo $fileinfo) { return str_replace('/', DIRECTORY_SEPARATOR, $fileinfo->getPathname()); }, iterator_to_array($iterator, false));

        $expected = array_map(function ($path) { return str_replace('/', DIRECTORY_SEPARATOR, $path); }, $expected);

        sort($values);
        sort($expected);

        $this->assertEquals($expected, array_values($values));
    }

    protected function assertOrderedIterator($expected, \Traversable $iterator)
    {
        $values = array_map(function (\SplFileInfo $fileinfo) { return $fileinfo->getPathname(); }, iterator_to_array($iterator));

        $this->assertEquals($expected, array_values($values));
    }

    /**
     * Same as IteratorTestCase::assertIterator with foreach usage
     *
     * @param array $expected
     * @param \Traversable $iterator
     */
    protected function assertIteratorInForeach($expected, \Traversable $iterator)
    {
        $values = array();
        foreach ($iterator as $file) {
            $this->assertInstanceOf('Symfony\\Component\\Finder\\SplFileInfo', $file);
            $values[] = $file->getPathname();
        }

        sort($values);
        sort($expected);

        $this->assertEquals($expected, array_values($values));
    }

    /**
     * Same as IteratorTestCase::assertOrderedIterator with foreach usage
     *
     * @param array $expected
     * @param \Traversable $iterator
     */
    protected function assertOrderedIteratorInForeach($expected, \Traversable $iterator)
    {
        $values = array();
        foreach ($iterator as $file) {
            $this->assertInstanceOf('Symfony\\Component\\Finder\\SplFileInfo', $file);
            $values[] = $file->getPathname();
        }

        $this->assertEquals($expected, array_values($values));
    }
}
