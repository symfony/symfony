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

use Symfony\Bundle\SecurityBundle\DataCollector\SecurityDataCollector;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('data_collector.security', SecurityDataCollector::class)
            ->args([
                service('security.untracked_token_storage'),
                service('security.role_hierarchy'),
                service('security.logout_url_generator'),
                service('security.access.decision_manager'),
                service('security.firewall.map'),
                service('debug.security.firewall')->nullOnInvalid(),
            ])
            ->tag('data_collector', [
                'template' => '@Security/Collector/security.html.twig',
                'id' => 'security',
                'priority' => 270,
            ])
    ;
};
