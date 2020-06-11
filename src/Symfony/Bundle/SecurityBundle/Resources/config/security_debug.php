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

use Symfony\Bundle\SecurityBundle\Debug\TraceableFirewallListener;
use Symfony\Bundle\SecurityBundle\EventListener\VoteListener;
use Symfony\Component\Security\Core\Authorization\TraceableAccessDecisionManager;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('debug.security.access.decision_manager', TraceableAccessDecisionManager::class)
            ->decorate('security.access.decision_manager')
            ->args([
                service('debug.security.access.decision_manager.inner'),
            ])

        ->set('debug.security.voter.vote_listener', VoteListener::class)
            ->tag('kernel.event_subscriber')
            ->args([
                service('debug.security.access.decision_manager'),
            ])

        ->set('debug.security.firewall', TraceableFirewallListener::class)
            ->tag('kernel.event_subscriber')
            ->args([
                service('security.firewall.map'),
                service('event_dispatcher'),
                service('security.logout_url_generator'),
            ])
        ->alias('security.firewall', 'debug.security.firewall')
    ;
};
