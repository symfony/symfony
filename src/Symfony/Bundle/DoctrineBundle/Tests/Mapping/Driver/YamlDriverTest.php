<?php

namespace Symfony\Bundle\DoctrineBundle\Tests\Mapping\Driver;

use Symfony\Bundle\DoctrineBundle\Mapping\Driver\YamlDriver;

class YamlDriverTest extends AbstractDriverTest
{
    protected function getFileExtension()
    {
        return '.orm.yml';
    }

    protected function getDriver(array $paths = array())
    {
        return new YamlDriver($paths);
    }
}