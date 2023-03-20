<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\JsonEncoder\DependencyInjection\JsonEncodablePass;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Annotations\AbstractDummy;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy;

class JsonEncodablePassTest extends TestCase
{
    public function testFindEncodableClasses()
    {
        $container = new ContainerBuilder();
        $container->setParameter('json_encoder.encodable_paths', [\dirname(__DIR__, 1).'/Fixtures/{Model,Invalid}']);

        (new JsonEncodablePass())->process($container);

        $encodable = [];
        foreach ($container->getDefinitions() as $definition) {
            if (!$definition->hasTag('json_encoder.encodable')) {
                continue;
            }

            $encodable[] = $definition->getClass();
        }

        $this->assertContains(ClassicDummy::class, $encodable);
        $this->assertNotContains(AbstractDummy::class, $encodable);
    }
}
