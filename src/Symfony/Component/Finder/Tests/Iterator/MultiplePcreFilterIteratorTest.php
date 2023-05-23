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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Iterator\MultiplePcreFilterIterator;

class MultiplePcreFilterIteratorTest extends TestCase
{
    /**
     * @dataProvider getIsRegexFixtures
     */
    public function testIsRegex($string, $isRegex, $message)
    {
        $testIterator = new TestMultiplePcreFilterIterator();
        $this->assertEquals($isRegex, $testIterator->isRegex($string), $message);
    }

    public static function getIsRegexFixtures()
    {
        yield ['foo', false, 'string'];
        yield [' foo ', false, '" " is not a valid delimiter'];
        yield ['\\foo\\', false, '"\\" is not a valid delimiter'];
        yield ['afooa', false, '"a" is not a valid delimiter'];
        yield ['//', false, 'the pattern should contain at least 1 character'];
        yield ['/a/', true, 'valid regex'];
        yield ['/foo/', true, 'valid regex'];
        yield ['/foo/i', true, 'valid regex with a single modifier'];
        yield ['/foo/imsxu', true, 'valid regex with multiple modifiers'];
        yield ['#foo#', true, '"#" is a valid delimiter'];
        yield ['{foo}', true, '"{,}" is a valid delimiter pair'];
        yield ['[foo]', true, '"[,]" is a valid delimiter pair'];
        yield ['(foo)', true, '"(,)" is a valid delimiter pair'];
        yield ['<foo>', true, '"<,>" is a valid delimiter pair'];
        yield ['*foo.*', false, '"*" is not considered as a valid delimiter'];
        yield ['?foo.?', false, '"?" is not considered as a valid delimiter'];
        yield ['/foo/n', true, 'valid regex with the no-capture modifier'];
    }
}

class TestMultiplePcreFilterIterator extends MultiplePcreFilterIterator
{
    public function __construct()
    {
    }

    public function accept(): bool
    {
        throw new \BadFunctionCallException('Not implemented');
    }

    public function isRegex(string $str): bool
    {
        return parent::isRegex($str);
    }

    public function toRegex(string $str): string
    {
        throw new \BadFunctionCallException('Not implemented');
    }
}
