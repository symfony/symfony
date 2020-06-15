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

use Symfony\Component\Translation\DataCollector\TranslationDataCollector;
use Symfony\Component\Translation\DataCollectorTranslator;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('translator.data_collector', DataCollectorTranslator::class)
            ->args([service('translator.data_collector.inner')])

        ->set('data_collector.translation', TranslationDataCollector::class)
            ->args([service('translator.data_collector')])
            ->tag('data_collector', [
                'template' => '@WebProfiler/Collector/translation.html.twig',
                'id' => 'translation',
                'priority' => 275,
            ])
    ;
};
