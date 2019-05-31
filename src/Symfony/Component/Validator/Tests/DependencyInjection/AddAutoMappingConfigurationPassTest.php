<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Validator\DependencyInjection\AddAutoMappingConfigurationPass;
use Symfony\Component\Validator\Tests\Fixtures\PropertyInfoLoaderEntity;
use Symfony\Component\Validator\ValidatorBuilder;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class AddAutoMappingConfigurationPassTest extends TestCase
{
    public function testNoConfigParameter()
    {
        $container = new ContainerBuilder();
        (new AddAutoMappingConfigurationPass())->process($container);
        $this->assertCount(1, $container->getDefinitions());
    }

    public function testNoValidatorBuilder()
    {
        $container = new ContainerBuilder();
        (new AddAutoMappingConfigurationPass())->process($container);
        $this->assertCount(1, $container->getDefinitions());
    }

    /**
     * @dataProvider mappingProvider
     */
    public function testProcess(string $namespace, array $services, string $expectedRegexp)
    {
        $container = new ContainerBuilder();
        $container->setParameter('validator.auto_mapping', [
            'App\\' => ['services' => []],
            $namespace => ['services' => $services],
        ]);

        $container->register('validator.builder', ValidatorBuilder::class);
        foreach ($services as $service) {
            $container->register($service)->addTag('validator.auto_mapper');
        }

        (new AddAutoMappingConfigurationPass())->process($container);

        foreach ($services as $service) {
            $this->assertSame($expectedRegexp, $container->getDefinition($service)->getArgument('$classValidatorRegexp'));
        }
        $this->assertCount(\count($services), $container->getDefinition('validator.builder')->getMethodCalls());
    }

    public function mappingProvider(): array
    {
        return [
            ['Foo\\', ['foo', 'baz'], '{^App\\\\|^Foo\\\\}'],
            [PropertyInfoLoaderEntity::class, ['class'], '{^App\\\\|^Symfony\\\\Component\\\\Validator\\\\Tests\\\\Fixtures\\\\PropertyInfoLoaderEntity$}'],
            ['Symfony\Component\Validator\Tests\Fixtures\\', ['trailing_antislash'], '{^App\\\\|^Symfony\\\\Component\\\\Validator\\\\Tests\\\\Fixtures\\\\}'],
            ['Symfony\Component\Validator\Tests\Fixtures\\*', ['trailing_star'], '{^App\\\\|^Symfony\\\\Component\\\\Validator\\\\Tests\\\\Fixtures\\\\[^\\\\]*?$}'],
            ['Symfony\Component\Validator\Tests\Fixtures\\**', ['trailing_double_star'], '{^App\\\\|^Symfony\\\\Component\\\\Validator\\\\Tests\\\\Fixtures\\\\.*?$}'],
        ];
    }

    public function testDoNotMapAllClassesWhenConfigIsEmpty()
    {
        $container = new ContainerBuilder();
        $container->setParameter('validator.auto_mapping', []);

        $container->register('validator.builder', ValidatorBuilder::class);
        $container->register('loader')->addTag('validator.auto_mapper');

        (new AddAutoMappingConfigurationPass())->process($container);

        $this->assertNull($container->getDefinition('loader')->getArgument('$classValidatorRegexp'));
    }
}
