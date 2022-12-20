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
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Glob;

class GlobTest extends TestCase
{
    public function testGlobToRegexDelimiters()
    {
        self::assertEquals('#^(?=[^\.])\#$#', Glob::toRegex('#'));
        self::assertEquals('#^\.[^/]*$#', Glob::toRegex('.*'));
        self::assertEquals('^\.[^/]*$', Glob::toRegex('.*', true, true, ''));
        self::assertEquals('/^\.[^/]*$/', Glob::toRegex('.*', true, true, '/'));
    }

    public function testGlobToRegexDoubleStarStrictDots()
    {
        $finder = new Finder();
        $finder->ignoreDotFiles(false);
        $regex = Glob::toRegex('/**/*.neon');

        foreach ($finder->in(__DIR__) as $k => $v) {
            $k = str_replace(\DIRECTORY_SEPARATOR, '/', $k);
            if (preg_match($regex, substr($k, \strlen(__DIR__)))) {
                $match[] = substr($k, 10 + \strlen(__DIR__));
            }
        }
        sort($match);

        self::assertSame(['one/b/c.neon', 'one/b/d.neon'], $match);
    }

    public function testGlobToRegexDoubleStarNonStrictDots()
    {
        $finder = new Finder();
        $finder->ignoreDotFiles(false);
        $regex = Glob::toRegex('/**/*.neon', false);

        foreach ($finder->in(__DIR__) as $k => $v) {
            $k = str_replace(\DIRECTORY_SEPARATOR, '/', $k);
            if (preg_match($regex, substr($k, \strlen(__DIR__)))) {
                $match[] = substr($k, 10 + \strlen(__DIR__));
            }
        }
        sort($match);

        self::assertSame(['.dot/b/c.neon', '.dot/b/d.neon', 'one/b/c.neon', 'one/b/d.neon'], $match);
    }

    public function testGlobToRegexDoubleStarWithoutLeadingSlash()
    {
        $finder = new Finder();
        $finder->ignoreDotFiles(false);
        $regex = Glob::toRegex('/Fixtures/one/**');

        foreach ($finder->in(__DIR__) as $k => $v) {
            $k = str_replace(\DIRECTORY_SEPARATOR, '/', $k);
            if (preg_match($regex, substr($k, \strlen(__DIR__)))) {
                $match[] = substr($k, 10 + \strlen(__DIR__));
            }
        }
        sort($match);

        self::assertSame(['one/a', 'one/b', 'one/b/c.neon', 'one/b/d.neon'], $match);
    }

    public function testGlobToRegexDoubleStarWithoutLeadingSlashNotStrictLeadingDot()
    {
        $finder = new Finder();
        $finder->ignoreDotFiles(false);
        $regex = Glob::toRegex('/Fixtures/one/**', false);

        foreach ($finder->in(__DIR__) as $k => $v) {
            $k = str_replace(\DIRECTORY_SEPARATOR, '/', $k);
            if (preg_match($regex, substr($k, \strlen(__DIR__)))) {
                $match[] = substr($k, 10 + \strlen(__DIR__));
            }
        }
        sort($match);

        self::assertSame(['one/.dot', 'one/a', 'one/b', 'one/b/c.neon', 'one/b/d.neon'], $match);
    }
}
