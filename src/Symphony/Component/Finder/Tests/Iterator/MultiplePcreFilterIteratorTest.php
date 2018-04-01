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

use PHPUnit\Framework\TestCase;
use Symphony\Component\Finder\Iterator\MultiplePcreFilterIterator;

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

    public function getIsRegexFixtures()
    {
        return array(
            array('foo', false, 'string'),
            array(' foo ', false, '" " is not a valid delimiter'),
            array('\\foo\\', false, '"\\" is not a valid delimiter'),
            array('afooa', false, '"a" is not a valid delimiter'),
            array('//', false, 'the pattern should contain at least 1 character'),
            array('/a/', true, 'valid regex'),
            array('/foo/', true, 'valid regex'),
            array('/foo/i', true, 'valid regex with a single modifier'),
            array('/foo/imsxu', true, 'valid regex with multiple modifiers'),
            array('#foo#', true, '"#" is a valid delimiter'),
            array('{foo}', true, '"{,}" is a valid delimiter pair'),
            array('[foo]', true, '"[,]" is a valid delimiter pair'),
            array('(foo)', true, '"(,)" is a valid delimiter pair'),
            array('<foo>', true, '"<,>" is a valid delimiter pair'),
            array('*foo.*', false, '"*" is not considered as a valid delimiter'),
            array('?foo.?', false, '"?" is not considered as a valid delimiter'),
        );
    }
}

class TestMultiplePcreFilterIterator extends MultiplePcreFilterIterator
{
    public function __construct()
    {
    }

    public function accept()
    {
        throw new \BadFunctionCallException('Not implemented');
    }

    public function isRegex($str)
    {
        return parent::isRegex($str);
    }

    public function toRegex($str)
    {
        throw new \BadFunctionCallException('Not implemented');
    }
}
