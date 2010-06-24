<?php

namespace Symfony\Tests\Components\Form;

class LocalizedTestCase extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('The "intl" extension is not available');
        }
    }
}