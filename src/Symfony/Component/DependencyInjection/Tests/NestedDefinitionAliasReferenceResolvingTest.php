<?php

namespace Symfony\Component\DependencyInjection\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class NestedDefinitionAliasReferenceResolvingTest extends TestCase
{
    public function getAliasStatus()
    {
        return [
            'true' => [true],
            'false' => [false],
        ];
    }

    /**
     * @dataProvider getAliasStatus
     *
     * @param bool $public
     */
    public function testDefinitionReferencesAlias($public)
    {
        $container = new ContainerBuilder();

        $container->register('original_dep', \stdClass::class);
        $container->setAlias('dependency', new Alias('original_dep', $public));

        $container->register('service', \stdClass::class)
            ->setArguments(
                [
                    (new Definition(\stdClass::class))
                        ->setArguments(
                            [
                                new Reference('dependency'),
                            ]
                        ),
                ]
            );

        $container->compile();
        $this->assertInstanceOf(\stdClass::class, $container->get('service'));
    }

    public function testDefinitionReferencesService()
    {
        $container = new ContainerBuilder();

        $container->register('original_dep', \stdClass::class);
        $container->setAlias('dependency', new Alias('original_dep', false));

        $container->register('service', \stdClass::class)
            ->setArguments(
                [
                    (new Definition(\stdClass::class))
                        ->setArguments(
                            [
                                new Reference('original_dep'),
                            ]
                        ),
                ]
            );

        $container->compile();
        $this->assertInstanceOf(\stdClass::class, $container->get('service'));
    }
}
