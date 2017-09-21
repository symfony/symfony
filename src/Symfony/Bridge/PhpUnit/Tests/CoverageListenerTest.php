<?php

namespace Symfony\Bridge\PhpUnit\Tests;

use PHPUnit\Framework\TestCase;

class CoverageListenerTest extends TestCase
{
    public function test()
    {
        if ("\n" !== PHP_EOL) {
            $this->markTestSkipped('This test cannot be run on Windows.');
        }

        $dir = __DIR__.'/../Tests-Fixtures/coverage';
        $php = PHP_BINARY;
        $phpunit = $_SERVER['argv'][0];

        exec("$php -d zend_extension=xdebug.so $phpunit -c $dir/phpunit-with-listener.xml.dist $dir/tests/ --coverage-text", $output);
        $output = implode("\n", $output);
        $this->assertNotContains('Foo', $output);

        exec("$php -d zend_extension=xdebug.so $phpunit -c $dir/phpunit-without-listener.xml.dist $dir/tests/ --coverage-text", $output);
        $output = implode("\n", $output);
        $this->assertContains('Foo', $output);
    }
}
