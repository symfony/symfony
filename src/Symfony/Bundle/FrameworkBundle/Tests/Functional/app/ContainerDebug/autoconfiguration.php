<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\AutoconfiguredAttribute;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\AutoconfiguredInterface;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\DeprecatedAutoconfiguredInterface;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\PercentTagged;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container, ContainerBuilder $builder) {
    $container->import('config.yml');

    $container->services()
        ->set(PercentTagged::class)
            ->autoconfigure();

    $builder->registerForAutoconfiguration(AutoconfiguredInterface::class)
        ->addTag('app.autoconfigured')
        ->addMethodCall('setFoo')
        ->setBindings(['$bar' => 'bar']);

    $builder->registerForAutoconfiguration(DeprecatedAutoconfiguredInterface::class)
        ->addTag('app.deprecated');

    $builder->registerAttributeForAutoconfiguration(AutoconfiguredAttribute::class, static function (ChildDefinition $definition) {
        $definition->addTag('app.autoconfigured_attribute');
    });
};
