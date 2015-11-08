<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests;

use Symfony\Bridge\PhpUnit\DeprecationErrorHandler;

class DeprecationErrorHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideRegisterParameters
     */
    public function testRegisterFalse($mode)
    {
        $this->assertNull(DeprecationErrorHandler::register($mode));
    }

    public function provideRegisterParameters()
    {
        return array(
            array(false),
            array(true),
        );
    }
}
