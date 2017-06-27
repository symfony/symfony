<?php

namespace Symfony\Component\Amqp\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    public static function setUpBeforeClass()
    {
        if (!extension_loaded('amqp')) {
            self::markTestSkipped('The amqp extension is required to execute this test.');
        }
    }
}
