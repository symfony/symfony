<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Finder\Tests\Iterator;

use Symphony\Component\Finder\Iterator\ExcludeDirectoryFilterIterator;
use Symphony\Component\Finder\Iterator\RecursiveDirectoryIterator;

class ExcludeDirectoryFilterIteratorTest extends RealIteratorTestCase
{
    /**
     * @dataProvider getAcceptData
     */
    public function testAccept($directories, $expected)
    {
        $inner = new \RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->toAbsolute(), \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);

        $iterator = new ExcludeDirectoryFilterIterator($inner, $directories);

        $this->assertIterator($expected, $iterator);
    }

    public function getAcceptData()
    {
        $foo = array(
            '.bar',
            '.foo',
            '.foo/.bar',
            '.foo/bar',
            '.git',
            'test.py',
            'test.php',
            'toto',
            'toto/.git',
            'foo bar',
        );

        $fo = array(
            '.bar',
            '.foo',
            '.foo/.bar',
            '.foo/bar',
            '.git',
            'test.py',
            'foo',
            'foo/bar.tmp',
            'test.php',
            'toto',
            'toto/.git',
            'foo bar',
        );

        $toto = array(
            '.bar',
            '.foo',
            '.foo/.bar',
            '.foo/bar',
            '.git',
            'test.py',
            'foo',
            'foo/bar.tmp',
            'test.php',
            'foo bar',
        );

        return array(
            array(array('foo'), $this->toAbsolute($foo)),
            array(array('fo'), $this->toAbsolute($fo)),
            array(array('toto/'), $this->toAbsolute($toto)),
        );
    }
}
