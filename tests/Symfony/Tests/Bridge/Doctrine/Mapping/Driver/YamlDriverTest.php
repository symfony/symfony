<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Tests\Bridge\Doctrine\Mapping\Driver;

use Symfony\Bridge\Doctrine\Mapping\Driver\YamlDriver;

class YamlDriverTest extends AbstractDriverTest
{
    protected function getFileExtension()
    {
        return '.orm.yml';
    }

    protected function getDriver(array $paths = array())
    {
        $driver = new YamlDriver(array_values($paths));
        $driver->setNamespacePrefixes(array_flip($paths));

        return $driver;
    }
}
