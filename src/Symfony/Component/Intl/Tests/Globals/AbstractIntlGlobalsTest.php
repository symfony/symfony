<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\Globals;

/**
 * Test case for intl function implementations.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractIntlGlobalsTest extends \PHPUnit_Framework_TestCase
{
    public function errorNameProvider()
    {
        return array (
            array(-129, '[BOGUS UErrorCode]'),
            array(0, 'U_ZERO_ERROR'),
            array(1, 'U_ILLEGAL_ARGUMENT_ERROR'),
            array(9, 'U_PARSE_ERROR'),
            array(129, '[BOGUS UErrorCode]'),
        );
    }

    /**
     * @dataProvider errorNameProvider
     */
    public function testGetErrorName($errorCode, $errorName)
    {
        $this->assertSame($errorName, $this->getIntlErrorName($errorCode));
    }

    abstract protected function getIntlErrorName($errorCode);
}
