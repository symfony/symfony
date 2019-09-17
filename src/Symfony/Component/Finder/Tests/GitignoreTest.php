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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Gitignore;

class GitignoreTest extends TestCase
{
    /**
     * @dataProvider provider
     */
    public function testCases(string $patterns, array $matchingCases, array $nonMatchingCases)
    {
        $regex = Gitignore::toRegex($patterns);

        foreach ($matchingCases as $matchingCase) {
            $this->assertRegExp($regex, $matchingCase, sprintf('Failed asserting path [%s] matches gitignore patterns [%s] using regex [%s]', $matchingCase, $patterns, $regex));
        }

        foreach ($nonMatchingCases as $nonMatchingCase) {
            $this->assertNotRegExp($regex, $nonMatchingCase, sprintf('Failed asserting path [%s] not matching gitignore patterns [%s] using regex [%s]', $nonMatchingCase, $patterns, $regex));
        }
    }

    /**
     * @return array return is array of
     *               [
     *               [
     *               '', // Git-ignore Pattern
     *               [], // array of file paths matching
     *               [], // array of file paths not matching
     *               ],
     *               ]
     */
    public function provider(): array
    {
        return [
            [
                '
                    *
                    !/bin/bash
                ',
                ['bin/cat', 'abc/bin/cat'],
                ['bin/bash'],
            ],
            [
                'fi#le.txt',
                [],
                ['#file.txt'],
            ],
            [
                '
                /bin/
                /usr/local/
                !/bin/bash
                !/usr/local/bin/bash
                ',
                ['bin/cat'],
                ['bin/bash'],
            ],
            [
                '*.py[co]',
                ['file.pyc', 'file.pyc'],
                ['filexpyc', 'file.pycx', 'file.py'],
            ],
            [
                'dir1/**/dir2/',
                ['dir1/dirA/dir2/', 'dir1/dirA/dirB/dir2/'],
                [],
            ],
            [
                'dir1/*/dir2/',
                ['dir1/dirA/dir2/'],
                ['dir1/dirA/dirB/dir2/'],
            ],
            [
                '/*.php',
                ['file.php'],
                ['app/file.php'],
            ],
            [
                '\#file.txt',
                ['#file.txt'],
                [],
            ],
            [
                '*.php',
                ['app/file.php', 'file.php'],
                ['file.phps', 'file.phps', 'filephps'],
            ],
            [
                'app/cache/',
                ['app/cache/file.txt', 'app/cache/dir1/dir2/file.txt', 'a/app/cache/file.txt'],
                [],
            ],
            [
                '
                #IamComment
                /app/cache/',
                ['app/cache/file.txt', 'app/cache/subdir/ile.txt'],
                ['a/app/cache/file.txt', '#IamComment', 'IamComment'],
            ],
            [
                '
                /app/cache/
                #LastLineIsComment',
                ['app/cache/file.txt', 'app/cache/subdir/ile.txt'],
                ['a/app/cache/file.txt', '#LastLineIsComment', 'LastLineIsComment'],
            ],
            [
                '
                /app/cache/
                \#file.txt
                #LastLineIsComment',
                ['app/cache/file.txt', 'app/cache/subdir/ile.txt', '#file.txt'],
                ['a/app/cache/file.txt', '#LastLineIsComment', 'LastLineIsComment'],
            ],
            [
                '
                /app/cache/
                \#file.txt
                #IamComment
                another_file.txt',
                ['app/cache/file.txt', 'app/cache/subdir/ile.txt', '#file.txt', 'another_file.txt'],
                ['a/app/cache/file.txt', 'IamComment', '#IamComment'],
            ],
        ];
    }
}
