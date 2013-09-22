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
        $exception = new ParseException('Error message', 42, 'foo: bar', '/var/www/app/config.yml');
        if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
            $message = 'Error message in "/var/www/app/config.yml" at line 42 (near "foo: bar")';
        } else {
            $message = 'Error message in "\\/var\\/www\\/app\\/config.yml" at line 42 (near "foo: bar")';
        }

        $this->assertEquals($message, $exception->getMessage());
    }
}
