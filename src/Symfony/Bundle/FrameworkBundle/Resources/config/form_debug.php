<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Form\Extension\DataCollector\FormDataCollector;
use Symfony\Component\Form\Extension\DataCollector\FormDataExtractor;
use Symfony\Component\Form\Extension\DataCollector\Proxy\ResolvedTypeFactoryDataCollectorProxy;
use Symfony\Component\Form\Extension\DataCollector\Type\DataCollectorTypeExtension;
use Symfony\Component\Form\ResolvedFormTypeFactory;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('form.resolved_type_factory', ResolvedTypeFactoryDataCollectorProxy::class)
            ->args([
                inline_service(ResolvedFormTypeFactory::class),
                service('data_collector.form'),
            ])

        ->set('form.type_extension.form.data_collector', DataCollectorTypeExtension::class)
            ->args([service('data_collector.form')])
            ->tag('form.type_extension')

        ->set('data_collector.form.extractor', FormDataExtractor::class)

        ->set('data_collector.form', FormDataCollector::class)
            ->args([service('data_collector.form.extractor')])
            ->tag('data_collector', ['template' => '@WebProfiler/Collector/form.html.twig', 'id' => 'form', 'priority' => 310])
    ;
};
