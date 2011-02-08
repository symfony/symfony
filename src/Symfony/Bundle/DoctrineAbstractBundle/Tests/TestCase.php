<?php

namespace Symfony\Bundle\DoctrineAbstractBundle\Tests;

class TestCase extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Doctrine\Common\DataFixtures\Loader')) {
            $this->markTestSkipped('Doctrine Data Fixtures is not available.');
        }
    }
}
