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

use PHPUnit\Framework\TestCase;

/**
 * Test case for intl function implementations.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractIntlGlobalsTest extends TestCase
{
    public function errorNameProvider()
    {
        return [
            [-129, '[BOGUS UErrorCode]'],
            [0, 'U_ZERO_ERROR'],
            [1, 'U_ILLEGAL_ARGUMENT_ERROR'],
            [9, 'U_PARSE_ERROR'],
            [129, '[BOGUS UErrorCode]'],
        ];
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
