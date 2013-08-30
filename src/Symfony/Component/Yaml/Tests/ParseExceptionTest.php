<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Yaml\Tests;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class ParseExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testGetMessage()
    {
        $exception = new ParseException(
            'Error message', 42, 'foo: bar', '/var/www/app/config.yml'
        );

        $this->assertEquals(
            'Error message in "/var/www/app/config.yml" at line 42 (near "foo: bar")',
            $exception->getMessage()
        );
    }
}
