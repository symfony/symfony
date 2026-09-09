<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Semaphore\SemaphoreBundle;

class ConfigurationTest extends TestCase
{
    #[DataProvider('provideValidSemaphoreConfigurationTests')]
    public function testValidSemaphoreConfiguration($semaphoreConfig, $processedConfig)
    {
        $this->assertEquals($processedConfig, $this->process($semaphoreConfig));
    }

    public static function provideValidSemaphoreConfigurationTests()
    {
        yield [null, ['enabled' => true, 'resources' => []]];
        yield [[], ['enabled' => true, 'resources' => []]];
        yield [true, ['enabled' => true, 'resources' => []]];
        yield [false, ['enabled' => false, 'resources' => []]];
        yield [['enabled' => false], ['enabled' => false, 'resources' => []]];

        yield ['redis://default', ['enabled' => true, 'resources' => ['default' => 'redis://default']]];
        yield [['foo' => 'redis://foo', 'bar' => 'redis://bar'], ['enabled' => true, 'resources' => ['foo' => 'redis://foo', 'bar' => 'redis://bar']]];
        yield [['default' => 'redis://default'], ['enabled' => true, 'resources' => ['default' => 'redis://default']]];

        yield [['enabled' => false, 'redis://default'], ['enabled' => false, 'resources' => ['default' => 'redis://default']]];
        yield [['enabled' => false, 'foo' => 'redis://foo', 'bar' => 'redis://bar'], ['enabled' => false, 'resources' => ['foo' => 'redis://foo', 'bar' => 'redis://bar']]];
        yield [['enabled' => false, 'default' => 'redis://default'], ['enabled' => false, 'resources' => ['default' => 'redis://default']]];

        yield [['resources' => 'redis://default'], ['enabled' => true, 'resources' => ['default' => 'redis://default']]];
        yield [['resources' => ['foo' => 'redis://foo', 'bar' => 'redis://bar']], ['enabled' => true, 'resources' => ['foo' => 'redis://foo', 'bar' => 'redis://bar']]];
        yield [['resources' => ['default' => 'redis://default']], ['enabled' => true, 'resources' => ['default' => 'redis://default']]];

        yield [['enabled' => false, 'resources' => 'redis://default'], ['enabled' => false, 'resources' => ['default' => 'redis://default']]];
        yield [['enabled' => false, 'resources' => ['foo' => 'redis://foo', 'bar' => 'redis://bar']], ['enabled' => false, 'resources' => ['foo' => 'redis://foo', 'bar' => 'redis://bar']]];
        yield [['enabled' => false, 'resources' => ['default' => 'redis://default']], ['enabled' => false, 'resources' => ['default' => 'redis://default']]];

        // xml

        yield [['resource' => ['redis://default']], ['enabled' => true, 'resources' => ['default' => 'redis://default']]];
        yield [['resource' => ['redis://default', ['name' => 'foo', 'value' => 'redis://default']]], ['enabled' => true, 'resources' => ['default' => 'redis://default', 'foo' => 'redis://default']]];
        yield [['resource' => [['name' => 'foo', 'value' => 'redis://default']]], ['enabled' => true, 'resources' => ['foo' => 'redis://default']]];
        yield [['resource' => [['name' => 'foo', 'value' => 'redis://default'], ['name' => 'bar', 'value' => 'redis://default']]], ['enabled' => true, 'resources' => ['foo' => 'redis://default', 'bar' => 'redis://default']]];

        yield [['enabled' => false, 'resource' => ['redis://default']], ['enabled' => false, 'resources' => ['default' => 'redis://default']]];
        yield [['enabled' => false, 'resource' => ['redis://default', ['name' => 'foo', 'value' => 'redis://default']]], ['enabled' => false, 'resources' => ['default' => 'redis://default', 'foo' => 'redis://default']]];
        yield [['enabled' => false, 'resource' => [['name' => 'foo', 'value' => 'redis://default']]], ['enabled' => false, 'resources' => ['foo' => 'redis://default']]];
        yield [['enabled' => false, 'resource' => [['name' => 'foo', 'value' => 'redis://foo'], ['name' => 'bar', 'value' => 'redis://bar']]], ['enabled' => false, 'resources' => ['foo' => 'redis://foo', 'bar' => 'redis://bar']]];
    }

    private function process(mixed $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(new SemaphoreBundle(), null, 'semaphore'), [$config]);
    }
}
