<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests;

use Symfony\Bridge\Doctrine\ManagerRegistry;

class TestManagerRegistry extends ManagerRegistry
{
    public function setTestContainer($container)
    {
        $this->container = $container;
    }

    public function getAliasNamespace($alias): string
    {
        return 'Foo';
    }
}
