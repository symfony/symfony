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
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\TypedReference;
use Symfony\Component\JsonEncoder\DecoderInterface;
use Symfony\Component\JsonEncoder\DependencyInjection\RuntimeServicesPass;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithAttributesUsingServices;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithFormatterAttributes;

class RuntimeServicesPassTest extends TestCase
{
    public function testRetrieveServices()
    {
        $container = new ContainerBuilder();

        $container->register(ClassicDummy::class, ClassicDummy::class)->addTag('json_encoder.encodable');
        $container->register(DummyWithFormatterAttributes::class, DummyWithFormatterAttributes::class)->addTag('json_encoder.encodable');
        $container->register(DummyWithAttributesUsingServices::class, DummyWithAttributesUsingServices::class)->addTag('json_encoder.encodable');

        (new RuntimeServicesPass())->process($container);

        $runtimeServices = $container->getDefinition('.json_encoder.runtime_services')->getArgument(0);

        $runtimeService = $runtimeServices[sprintf('%s::serviceAndConfig[service]', DummyWithAttributesUsingServices::class)];
        $this->assertInstanceOf(ServiceClosureArgument::class, $runtimeService);
        $this->assertEquals([new TypedReference(DecoderInterface::class, DecoderInterface::class, name: 'service')], $runtimeService->getValues());

        $runtimeService = $runtimeServices[sprintf('%s::autowireAttribute[service]', DummyWithAttributesUsingServices::class)];
        $this->assertInstanceOf(ServiceClosureArgument::class, $runtimeService);
        $this->assertEquals([new Reference('custom_service')], $runtimeService->getValues());

        $this->assertArrayNotHasKey(sprintf('%s::skippedUnknownService[skipped]', DummyWithAttributesUsingServices::class), $runtimeServices);

        $this->assertArrayNotHasKey(sprintf('%s::serviceAndConfig[config]', DummyWithAttributesUsingServices::class), $runtimeServices);

        $this->assertArrayNotHasKey(sprintf('%s::serviceAndConfig[value]', DummyWithAttributesUsingServices::class), $runtimeServices);
        $this->assertArrayNotHasKey(sprintf('%s::autowireAttribute[value]', DummyWithAttributesUsingServices::class), $runtimeServices);
        $this->assertArrayNotHasKey(sprintf('%s::skippedUnknownService[value]', DummyWithAttributesUsingServices::class), $runtimeServices);
    }
}
