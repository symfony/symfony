<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Util;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Tests\Fixtures\BadParent;
use Symfony\Component\DependencyInjection\Util\ServiceTypeHelper;

class ServiceTypeHelperTest extends \PHPUnit_Framework_TestCase
{
    public function testIgnoreServiceWithClassNotExisting()
    {
        $container = new ContainerBuilder();
        $container->register('class_not_exist', 'NotExistingClass');

        $helper = new ServiceTypeHelper($container);
        $this->assertEmpty($helper->getOfType('NotExistingClass'));
    }

    public function testIgnoreServiceWithParentNotExisting()
    {
        $container = new ContainerBuilder();
        $container->register('bad_parent', BadParent::class);

        $helper = new ServiceTypeHelper($container);
        $this->assertEmpty($helper->getOfType(BadParent::class));
    }
}
