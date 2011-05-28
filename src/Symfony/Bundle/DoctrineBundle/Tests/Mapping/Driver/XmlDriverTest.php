<?php

namespace Symfony\Bundle\DoctrineBundle\Tests\Mapping\Driver;

use Symfony\Bundle\DoctrineBundle\Mapping\Driver\XmlDriver;

class XmlDriverTest extends AbstractDriverTest
{
    protected function getFileExtension()
    {
        return '.orm.xml';
    }

    protected function getDriver(array $paths = array())
    {
        return new XmlDriver($paths);
    }
}