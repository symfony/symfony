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

/**
 * @author Michael Voříšek <vorismi3@fel.cvut.cz>
 */
class GitignoreTest extends TestCase
{
    /**
     * @dataProvider provider
     * @dataProvider providerExtended
     */
    public function testToRegex(array $gitignoreLines, array $matchingCases, array $nonMatchingCases)
    {
        $patterns = implode("\n", $gitignoreLines);

        $regex = Gitignore::toRegex($patterns);
        $this->assertSame($regex, Gitignore::toRegex(implode("\r\n", $gitignoreLines)));
        $this->assertSame($regex, Gitignore::toRegex(implode("\r", $gitignoreLines)));

        foreach ($matchingCases as $matchingCase) {
            $this->assertMatchesRegularExpression(
                $regex,
                $matchingCase,
                sprintf(
                    "Failed asserting path:\n%s\nmatches gitignore patterns:\n%s",
                    preg_replace('~^~m', '    ', $matchingCase),
                    preg_replace('~^~m', '    ', $patterns)
                )
            );
        }

        foreach ($nonMatchingCases as $nonMatchingCase) {
            $this->assertDoesNotMatchRegularExpression(
                $regex,
                $nonMatchingCase,
                sprintf("Failed asserting path:\n%s\nNOT matching gitignore patterns:\n%s",
                    preg_replace('~^~m', '    ', $nonMatchingCase),
                    preg_replace('~^~m', '    ', $patterns)
                )
            );
        }
    }

    public static function provider(): array
    {
        $cases = [
            [
                [''],
                [],
                ['a', 'a/b', 'a/b/c', 'aa', 'm.txt', '.txt'],
            ],
            [
                ['a', 'X'],
                ['a', 'a/b', 'a/b/c', 'X', 'b/a', 'b/c/a', 'a/X', 'a/X/y', 'b/a/X/y'],
                ['A', 'x', 'aa', 'm.txt', '.txt', 'aa/b', 'b/aa'],
            ],
            [
                ['/a', 'x', 'd/'],
                ['a', 'a/b', 'a/b/c', 'x', 'a/x', 'a/x/y', 'b/a/x/y', 'd/', 'd/u', 'e/d/', 'e/d/u'],
                ['b/a', 'b/c/a', 'aa', 'm.txt', '.txt', 'aa/b', 'b/aa', 'e/d'],
            ],
            [
                ['a/', 'x'],
                ['a/b', 'a/b/c', 'x', 'a/x', 'a/x/y', 'b/a/x/y'],
                ['a', 'b/a', 'b/c/a', 'aa', 'm.txt', '.txt', 'aa/b', 'b/aa'],
            ],
            [
                ['*'],
                ['a', 'a/b', 'a/b/c', 'aa', 'm.txt', '.txt'],
                [],
            ],
            [
                ['/*'],
                ['a', 'a/b', 'a/b/c', 'aa', 'm.txt', '.txt'],
                [],
            ],
            [
                ['/a', 'm/*', 'o/**', 'p/**/', 'x**y'],
                ['a', 'a/b', 'a/b/c', 'm/', 'o/', 'p/', 'xy', 'xuy', 'x/y', 'x/u/y', 'xu/y', 'x/uy', 'xu/uy'],
                ['aa', 'm', 'b/m', 'b/m/', 'o', 'b/o', 'b/o/', 'p', 'b/p', 'b/p/'],
            ],
            [
                ['a', '!x'],
                ['a', 'a/b', 'a/b/c', 'b/a', 'b/c/a'],
                ['x', 'aa', 'm.txt', '.txt', 'aa/b', 'b/aa'],
            ],
            [
                ['a', '!a/', 'b', '!b/b'],
                ['a', 'a/x', 'x/a', 'x/a/x', 'b', 'b'],
                ['a/', 'x/a/', 'bb', 'b/b', 'bb'],
            ],
            [
                ['[a-c]', 'x[C-E][][o]', 'g-h'],
                ['a', 'b', 'c', 'xDo', 'g-h'],
                ['A', 'xdo', 'u', 'g', 'h'],
            ],
            [
                ['a?', '*/??b?'],
                ['ax', 'x/xxbx'],
                ['a', 'axy', 'xxax', 'x/xxax', 'x/y/xxax'],
            ],
            [
                [' ', ' \ ', '  \  ', '/a ', '/b/c \ '],
                ['  ', '   ', 'x/  ', 'x/   ', 'a', 'a/x', 'b/c  '],
                [' ', '    ', 'x/ ', 'x/    ', 'a ', 'b/c   '],
            ],
            [
                ['#', ' #', '/ #', '  #', '/  #', '  \ #', '   \  #', 'a #', 'a  #', 'a  \ #', 'a   \  #'],
                ['   ', '    ', 'a', 'a   ', 'a    '],
                [' ', '  ', 'a ', 'a  '],
            ],
            [
                ["\t", "\t\\\t", " \t\\\t ", "\t#", "a\t#", "a\t\t#", "a \t#", "a\t\t\\\t#", "a \t\t\\\t\t#"],
                ["\t\t", " \t\t", 'a', "a\t\t\t", "a \t\t\t"],
                ["\t", "\t\t ", " \t\t ", "a\t", 'a ', "a \t", "a\t\t"],
            ],
            [
                [' a', 'b ', '\ ', 'c\ '],
                [' a', 'b', ' ', 'c '],
                ['a', 'b ', 'c'],
            ],
            [
                ['#a', '\#b', '\#/'],
                ['#b', '#/'],
                ['#a', 'a', 'b'],
            ],
            [
                ['*', '!!', '!!*x', '\!!b'],
                ['a', '!!', '!!b'],
                ['!', '!x', '!xx'],
            ],
            [
                [
                    '*',
                    '!/bin',
                    '!/bin/bash',
                ],
                ['bin/cat', 'abc/bin/cat'],
                ['bin/bash'],
            ],
            [
                ['fi#le.txt'],
                [],
                ['#file.txt'],
            ],
            [
                [
                    '/bin/',
                    '/usr/local/',
                    '!/bin/bash',
                    '!/usr/local/bin/bash',
                ],
                ['bin/cat'],
                ['bin/bash'],
            ],
            [
                ['*.py[co]'],
                ['file.pyc', 'file.pyc'],
                ['filexpyc', 'file.pycx', 'file.py'],
            ],
            [
                ['dir1/**/dir2/'],
                ['dir1/dir2/', 'dir1/dirA/dir2/', 'dir1/dirA/dirB/dir2/'],
                ['dir1dir2/', 'dir1xdir2/', 'dir1/xdir2/', 'dir1x/dir2/'],
            ],
            [
                ['dir1/*/dir2/'],
                ['dir1/dirA/dir2/'],
                ['dir1/dirA/dirB/dir2/'],
            ],
            [
                ['/*.php'],
                ['file.php'],
                ['app/file.php'],
            ],
            [
                ['\#file.txt'],
                ['#file.txt'],
                [],
            ],
            [
                ['*.php'],
                ['app/file.php', 'file.php'],
                ['file.phps', 'file.phps', 'filephps'],
            ],
            [
                ['app/cache/'],
                ['app/cache/file.txt', 'app/cache/dir1/dir2/file.txt'],
                ['a/app/cache/file.txt'],
            ],
            [
                ['#IamComment', '/app/cache/'],
                ['app/cache/file.txt', 'app/cache/subdir/ile.txt'],
                ['a/app/cache/file.txt', '#IamComment', 'IamComment'],
            ],
            [
                ['/app/cache/', '#LastLineIsComment'],
                ['app/cache/file.txt', 'app/cache/subdir/ile.txt'],
                ['a/app/cache/file.txt', '#LastLineIsComment', 'LastLineIsComment'],
            ],
            [
                ['/app/cache/', '\#file.txt', '#LastLineIsComment'],
                ['app/cache/file.txt', 'app/cache/subdir/ile.txt', '#file.txt'],
                ['a/app/cache/file.txt', '#LastLineIsComment', 'LastLineIsComment'],
            ],
            [
                ['/app/cache/', '\#file.txt', '#IamComment', 'another_file.txt'],
                ['app/cache/file.txt', 'app/cache/subdir/ile.txt', '#file.txt', 'another_file.txt'],
                ['a/app/cache/file.txt', 'IamComment', '#IamComment'],
            ],
            [
                [
                    '/app/**',
                    '!/app/bin',
                    '!/app/bin/test',
                ],
                ['app/test/file', 'app/bin/file'],
                ['app/bin/test'],
            ],
            [
                [
                    '/app/*/img',
                    '!/app/*/img/src',
                ],
                ['app/a/img', 'app/a/img/x', 'app/a/img/src/x'],
                ['app/a/img/src', 'app/a/img/src/'],
            ],
            [
                [
                    'app/**/img',
                    '!/app/**/img/src',
                ],
                ['app/a/img', 'app/a/img/x', 'app/a/img/src/x', 'app/a/b/img', 'app/a/b/img/x', 'app/a/b/img/src/x', 'app/a/b/c/img'],
                ['app/a/img/src', 'app/a/b/img/src', 'app/a/c/b/img/src'],
            ],
            [
                [
                    '/*',
                    '!/foo',
                    '/foo/*',
                    '!/foo/bar',
                ],
                ['bar', 'foo/ba', 'foo/barx', 'x/foo/bar'],
                ['foo', 'foo/bar'],
            ],
            [
                [
                    '/example/**',
                    '!/example/example.txt',
                    '!/example/packages',
                ],
                ['example/test', 'example/example.txt2', 'example/packages/foo.yaml'],
                ['example/example.txt', 'example/packages', 'example/packages/'],
            ],
            // based on https://www.atlassian.com/git/tutorials/saving-changes/gitignore
            [
                ['**/logs'],
                ['logs/debug.log', 'logs/monday/foo.bar'],
                [],
            ],
            [
                ['**/logs/debug.log'],
                ['logs/debug.log', 'build/logs/debug.log'],
                ['logs/build/debug.log'],
            ],
            [
                ['*.log'],
                ['debug.log', 'foo.log', '.log', 'logs/debug.log'],
                [],
            ],
            [
                [
                    '*.log',
                    '!important.log',
                ],
                ['debug.log', 'trace.log'],
                ['important.log', 'logs/important.log'],
            ],
            [
                [
                    '*.log',
                    '!important/*.log',
                    'trace.*',
                ],
                ['debug.log', 'important/trace.log'],
                ['important/debug.log'],
            ],
            [
                ['/debug.log'],
                ['debug.log'],
                ['logs/debug.log'],
            ],
            [
                ['debug.log'],
                ['debug.log', 'logs/debug.log'],
                [],
            ],
            [
                ['debug?.log'],
                ['debug0.log', 'debugg.log'],
                ['debug10.log'],
            ],
            [
                ['debug[0-9].log'],
                ['debug0.log', 'debug1.log'],
                ['debug10.log'],
            ],
            [
                ['debug[01].log'],
                ['debug0.log', 'debug1.log'],
                ['debug2.log', 'debug01.log'],
            ],
            [
                ['debug[!01].log'],
                ['debug2.log'],
                ['debug0.log', 'debug1.log', 'debug01.log'],
            ],
            [
                ['debug[a-z].log'],
                ['debuga.log', 'debugb.log'],
                ['debug1.log'],
            ],
            [
                ['logs'],
                ['logs', 'logs/debug.log', 'logs/latest/foo.bar', 'build/logs', 'build/logs/debug.log'],
                [],
            ],
            [
                ['logs/'],
                ['logs/debug.log', 'logs/latest/foo.bar', 'build/logs/foo.bar', 'build/logs/latest/debug.log'],
                [],
            ],
            [
                [
                    'logs/',
                    '!logs/important.log',
                ],
                ['logs/debug.log'/* must be pruned on traversal 'logs/important.log' */],
                [],
            ],
            [
                ['logs/**/debug.log'],
                ['logs/debug.log', 'logs/monday/debug.log', 'logs/monday/pm/debug.log'],
                [],
            ],
            [
                ['logs/*day/debug.log'],
                ['logs/monday/debug.log', 'logs/tuesday/debug.log'],
                ['logs/latest/debug.log'],
            ],
            [
                ['logs/debug.log'],
                ['logs/debug.log'],
                ['debug.log', 'build/logs/debug.log'],
            ],
            [
                ['*/vendor/*'],
                ['a/vendor/', 'a/vendor/b', 'a/vendor/b/c'],
                ['a', 'vendor', 'vendor/', 'a/vendor', 'a/b/vendor', 'a/b/vendor/c'],
            ],
            [
                ['**/vendor/**'],
                ['vendor/', 'vendor/a', 'vendor/a/b', 'a/b/vendor/c/d'],
                ['a', 'vendor', 'a/vendor', 'a/b/vendor'],
            ],
            [
                ['***/***/vendor/*****/*****'],
                ['vendor/', 'vendor/a', 'vendor/a/b', 'a/b/vendor/c/d'],
                ['a', 'vendor', 'a/vendor', 'a/b/vendor'],
            ],
            [
                ['**vendor**'],
                ['vendor', 'vendor/', 'vendor/a', 'vendor/a/b', 'a/vendor', 'a/b/vendor', 'a/b/vendor/c/d'],
                ['a'],
            ],
        ];

        return $cases;
    }

    public static function providerExtended(): array
    {
        $basicCases = self::provider();

        $cases = [];
        foreach ($basicCases as $case) {
            $cases[] = [
                array_merge(['never'], $case[0], ['!never']),
                $case[1],
                $case[2],
            ];

            $cases[] = [
                array_merge(['!*'], $case[0]),
                $case[1],
                $case[2],
            ];

            $cases[] = [
                array_merge(['*', '!*'], $case[0]),
                $case[1],
                $case[2],
            ];

            $cases[] = [
                array_merge(['never', '**/never2', 'never3/**'], $case[0]),
                $case[1],
                $case[2],
            ];

            $cases[] = [
                array_merge(['!never', '!**/never2', '!never3/**'], $case[0]),
                $case[1],
                $case[2],
            ];

            $lines = [];
            for ($i = 0; $i < 30; ++$i) {
                foreach ($case[0] as $line) {
                    $lines[] = $line;
                }
            }
            $cases[] = [
                array_merge(['!never', '!**/never2', '!never3/**'], $lines),
                $case[1],
                $case[2],
            ];
        }

        return $cases;
    }

    /**
     * @dataProvider provideNegatedPatternsCases
     */
    public function testToRegexMatchingNegatedPatterns(array $gitignoreLines, array $matchingCases, array $nonMatchingCases)
    {
        $patterns = implode("\n", $gitignoreLines);

        $regex = Gitignore::toRegexMatchingNegatedPatterns($patterns);
        $this->assertSame($regex, Gitignore::toRegexMatchingNegatedPatterns(implode("\r\n", $gitignoreLines)));
        $this->assertSame($regex, Gitignore::toRegexMatchingNegatedPatterns(implode("\r", $gitignoreLines)));

        foreach ($matchingCases as $matchingCase) {
            $this->assertMatchesRegularExpression(
                $regex,
                $matchingCase,
                sprintf(
                    "Failed asserting path:\n%s\nmatches gitignore negated patterns:\n%s",
                    preg_replace('~^~m', '    ', $matchingCase),
                    preg_replace('~^~m', '    ', $patterns)
                )
            );
        }

        foreach ($nonMatchingCases as $nonMatchingCase) {
            $this->assertDoesNotMatchRegularExpression(
                $regex,
                $nonMatchingCase,
                sprintf("Failed asserting path:\n%s\nNOT matching gitignore negated patterns:\n%s",
                    preg_replace('~^~m', '    ', $nonMatchingCase),
                    preg_replace('~^~m', '    ', $patterns)
                )
            );
        }
    }

    public static function provideNegatedPatternsCases(): iterable
    {
        yield [
            [''],
            [],
            ['a', 'a/b', 'a/b/c', 'aa', 'm.txt', '.txt'],
        ];

        yield [
            ['!a', '!X'],
            ['a', 'a/b', 'a/b/c', 'X', 'b/a', 'b/c/a', 'a/X', 'a/X/y', 'b/a/X/y'],
            ['A', 'x', 'aa', 'm.txt', '.txt', 'aa/b', 'b/aa'],
        ];

        yield [
            ['!/a', '!x', '!d/'],
            ['a', 'a/b', 'a/b/c', 'x', 'a/x', 'a/x/y', 'b/a/x/y', 'd/', 'd/u', 'e/d/', 'e/d/u'],
            ['b/a', 'b/c/a', 'aa', 'm.txt', '.txt', 'aa/b', 'b/aa', 'e/d'],
        ];

        yield [
            ['!a/', '!x'],
            ['a/b', 'a/b/c', 'x', 'a/x', 'a/x/y', 'b/a/x/y'],
            ['a', 'b/a', 'b/c/a', 'aa', 'm.txt', '.txt', 'aa/b', 'b/aa'],
        ];

        yield [
            ['!*'],
            ['a', 'a/b', 'a/b/c', 'aa', 'm.txt', '.txt'],
            [],
        ];

        yield [
            ['!/*'],
            ['a', 'a/b', 'a/b/c', 'aa', 'm.txt', '.txt'],
            [],
        ];

        yield [
            ['!/a', '!m/*', '!o/**', '!p/**/', '!x**y'],
            ['a', 'a/b', 'a/b/c', 'm/', 'o/', 'p/', 'xy', 'xuy', 'x/y', 'x/u/y', 'xu/y', 'x/uy', 'xu/uy'],
            ['aa', 'm', 'b/m', 'b/m/', 'o', 'b/o', 'b/o/', 'p', 'b/p', 'b/p/'],
        ];

        yield [
            ['!a', 'x'],
            ['a', 'a/b', 'a/b/c', 'b/a', 'b/c/a'],
            ['x', 'aa', 'm.txt', '.txt', 'aa/b', 'b/aa'],
        ];

        yield [
            ['!a', 'a/', '!b', 'b/b'],
            ['a', 'a/x', 'x/a', 'x/a/x', 'b', 'b'],
            ['a/', 'x/a/', 'bb', 'b/b', 'bb'],
        ];

        yield [
            ['![a-c]', '!x[C-E][][o]', '!g-h'],
            ['a', 'b', 'c', 'xDo', 'g-h'],
            ['A', 'xdo', 'u', 'g', 'h'],
        ];

        yield [
            ['!a?', '!*/??b?'],
            ['ax', 'x/xxbx'],
            ['a', 'axy', 'xxax', 'x/xxax', 'x/y/xxax'],
        ];

        yield [
            ['! ', '! \ ', '!  \  ', '!/a ', '!/b/c \ '],
            ['  ', '   ', 'x/  ', 'x/   ', 'a', 'a/x', 'b/c  '],
            [' ', '    ', 'x/ ', 'x/    ', 'a ', 'b/c   '],
        ];

        yield [
            ['!\#', '! #', '!/ #', '!  #', '!/  #', '!  \ #', '!   \  #', '!a #', '!a  #', '!a  \ #', '!a   \  #'],
            ['   ', '    ', 'a', 'a   ', 'a    '],
            [' ', '  ', 'a ', 'a  '],
        ];

        yield [
            ["!\t", "!\t\\\t", "! \t\\\t ", "!\t#", "!a\t#", "!a\t\t#", "!a \t#", "!a\t\t\\\t#", "!a \t\t\\\t\t#"],
            ["\t\t", " \t\t", 'a', "a\t\t\t", "a \t\t\t"],
            ["\t", "\t\t ", " \t\t ", "a\t", 'a ', "a \t", "a\t\t"],
        ];

        yield [
            ['! a', '!b ', '!\ ', '!c\ '],
            [' a', 'b', ' ', 'c '],
            ['a', 'b ', 'c'],
        ];

        yield [
            ['!\#a', '!\#b', '!\#/'],
            ['#a', '#b', '#/'],
            ['a', 'b'],
        ];

        yield [
            ['*', '!!', '!!*x', '\!!b'],
            ['!', '!x', '!xx'],
            ['a', '!!', '!!b'],
        ];

        yield [
            [
                '*',
                '!/bin',
                '!/bin/bash',
            ],
            ['bin/bash', 'bin/cat'],
            ['abc/bin/cat'],
        ];

        yield [
            ['!fi#le.txt'],
            [],
            ['#file.txt'],
        ];

        yield [
            [
                '/bin/',
                '/usr/local/',
                '!/bin/bash',
                '!/usr/local/bin/bash',
            ],
            ['bin/bash'],
            ['bin/cat'],
        ];

        yield [
            ['!*.py[co]'],
            ['file.pyc', 'file.pyc'],
            ['filexpyc', 'file.pycx', 'file.py'],
        ];

        yield [
            ['!dir1/**/dir2/'],
            ['dir1/dir2/', 'dir1/dirA/dir2/', 'dir1/dirA/dirB/dir2/'],
            ['dir1dir2/', 'dir1xdir2/', 'dir1/xdir2/', 'dir1x/dir2/'],
        ];

        yield [
            ['!dir1/*/dir2/'],
            ['dir1/dirA/dir2/'],
            ['dir1/dirA/dirB/dir2/'],
        ];

        yield [
            ['!/*.php'],
            ['file.php'],
            ['app/file.php'],
        ];

        yield [
            ['!\#file.txt'],
            ['#file.txt'],
            [],
        ];

        yield [
            ['!*.php'],
            ['app/file.php', 'file.php'],
            ['file.phps', 'file.phps', 'filephps'],
        ];

        yield [
            ['!app/cache/'],
            ['app/cache/file.txt', 'app/cache/dir1/dir2/file.txt'],
            ['a/app/cache/file.txt'],
        ];

        yield [
            ['#IamComment', '!/app/cache/'],
            ['app/cache/file.txt', 'app/cache/subdir/ile.txt'],
            ['a/app/cache/file.txt', '#IamComment', 'IamComment'],
        ];

        yield [
            ['!/app/cache/', '#LastLineIsComment'],
            ['app/cache/file.txt', 'app/cache/subdir/ile.txt'],
            ['a/app/cache/file.txt', '#LastLineIsComment', 'LastLineIsComment'],
        ];

        yield [
            ['!/app/cache/', '!\#file.txt', '#LastLineIsComment'],
            ['app/cache/file.txt', 'app/cache/subdir/ile.txt', '#file.txt'],
            ['a/app/cache/file.txt', '#LastLineIsComment', 'LastLineIsComment'],
        ];

        yield [
            ['!/app/cache/', '!\#file.txt', '#IamComment', '!another_file.txt'],
            ['app/cache/file.txt', 'app/cache/subdir/ile.txt', '#file.txt', 'another_file.txt'],
            ['a/app/cache/file.txt', 'IamComment', '#IamComment'],
        ];

        yield [
            [
                '/app/**',
                '!/app/bin',
                '!/app/bin/test',
            ],
            ['app/bin/file', 'app/bin/test'],
            ['app/test/file'],
        ];

        yield [
            [
                '/app/*/img',
                '!/app/*/img/src',
            ],
            ['app/a/img/src', 'app/a/img/src/', 'app/a/img/src/x'],
            ['app/a/img', 'app/a/img/x'],
        ];

        yield [
            [
                'app/**/img',
                '!/app/**/img/src',
            ],
            ['app/a/img/src', 'app/a/b/img/src', 'app/a/c/b/img/src', 'app/a/img/src/x', 'app/a/b/img/src/x'],
            ['app/a/img', 'app/a/img/x', 'app/a/b/img', 'app/a/b/img/x', 'app/a/b/c/img'],
        ];

        yield [
            [
                '/*',
                '!/foo',
                '/foo/*',
                '!/foo/bar',
            ],
            ['foo', 'foo/bar'],
            ['bar', 'foo/ba', 'foo/barx', 'x/foo/bar'],
        ];

        yield [
            [
                '/example/**',
                '!/example/example.txt',
                '!/example/packages',
            ],
            ['example/example.txt', 'example/packages', 'example/packages/', 'example/packages/foo.yaml'],
            ['example/test', 'example/example.txt2'],
        ];

        // based on https://www.atlassian.com/git/tutorials/saving-changes/gitignore
        yield [
            ['!**/logs'],
            ['logs/debug.log', 'logs/monday/foo.bar'],
            [],
        ];

        yield [
            ['!**/logs/debug.log'],
            ['logs/debug.log', 'build/logs/debug.log'],
            ['logs/build/debug.log'],
        ];

        yield [
            ['!*.log'],
            ['debug.log', 'foo.log', '.log', 'logs/debug.log'],
            [],
        ];

        yield [
            [
                '*.log',
                '!important.log',
            ],
            ['important.log', 'logs/important.log'],
            ['debug.log', 'trace.log'],
        ];

        yield [
            [
                '*.log',
                '!important/*.log',
                'trace.*',
            ],
            ['important/debug.log'],
            ['debug.log', 'important/trace.log'],
        ];

        yield [
            ['!/debug.log'],
            ['debug.log'],
            ['logs/debug.log'],
        ];

        yield [
            ['!debug.log'],
            ['debug.log', 'logs/debug.log'],
            [],
        ];

        yield [
            ['!debug?.log'],
            ['debug0.log', 'debugg.log'],
            ['debug10.log'],
        ];

        yield [
            ['!debug[0-9].log'],
            ['debug0.log', 'debug1.log'],
            ['debug10.log'],
        ];

        yield [
            ['!debug[01].log'],
            ['debug0.log', 'debug1.log'],
            ['debug2.log', 'debug01.log'],
        ];

        yield [
            ['!debug[!01].log'],
            ['debug2.log'],
            ['debug0.log', 'debug1.log', 'debug01.log'],
        ];

        yield [
            ['!debug[a-z].log'],
            ['debuga.log', 'debugb.log'],
            ['debug1.log'],
        ];

        yield [
            ['!logs'],
            ['logs', 'logs/debug.log', 'logs/latest/foo.bar', 'build/logs', 'build/logs/debug.log'],
            [],
        ];

        yield [
            ['!logs/'],
            ['logs/debug.log', 'logs/latest/foo.bar', 'build/logs/foo.bar', 'build/logs/latest/debug.log'],
            [],
        ];

        yield [
            [
                'logs/',
                '!logs/important.log',
            ],
            [],
            ['logs/debug.log'/* must be pruned on traversal 'logs/important.log' */],
        ];

        yield [
            ['!logs/**/debug.log'],
            ['logs/debug.log', 'logs/monday/debug.log', 'logs/monday/pm/debug.log'],
            [],
        ];

        yield [
            ['!logs/*day/debug.log'],
            ['logs/monday/debug.log', 'logs/tuesday/debug.log'],
            ['logs/latest/debug.log'],
        ];

        yield [
            ['!logs/debug.log'],
            ['logs/debug.log'],
            ['debug.log', 'build/logs/debug.log'],
        ];

        yield [
            ['!*/vendor/*'],
            ['a/vendor/', 'a/vendor/b', 'a/vendor/b/c'],
            ['a', 'vendor', 'vendor/', 'a/vendor', 'a/b/vendor', 'a/b/vendor/c'],
        ];

        yield [
            ['!**/vendor/**'],
            ['vendor/', 'vendor/a', 'vendor/a/b', 'a/b/vendor/c/d'],
            ['a', 'vendor', 'a/vendor', 'a/b/vendor'],
        ];

        yield [
            ['!***/***/vendor/*****/*****'],
            ['vendor/', 'vendor/a', 'vendor/a/b', 'a/b/vendor/c/d'],
            ['a', 'vendor', 'a/vendor', 'a/b/vendor'],
        ];

        yield [
            ['!**vendor**'],
            ['vendor', 'vendor/', 'vendor/a', 'vendor/a/b', 'a/vendor', 'a/b/vendor', 'a/b/vendor/c/d'],
            ['a'],
        ];
    }
}
