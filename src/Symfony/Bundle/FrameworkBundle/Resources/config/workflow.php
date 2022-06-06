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

use Symfony\Component\Workflow\EventListener\ExpressionLanguage;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Workflow;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('workflow.abstract', Workflow::class)
            ->args([
                abstract_arg('workflow definition'),
                abstract_arg('marking store'),
                service('event_dispatcher')->ignoreOnInvalid(),
                abstract_arg('workflow name'),
                abstract_arg('events to dispatch'),
            ])
            ->abstract()
            ->public()
            ->tag('container.private', ['package' => 'symfony/framework-bundle', 'version' => '5.3'])
        ->set('state_machine.abstract', StateMachine::class)
            ->args([
                abstract_arg('workflow definition'),
                abstract_arg('marking store'),
                service('event_dispatcher')->ignoreOnInvalid(),
                abstract_arg('workflow name'),
                abstract_arg('events to dispatch'),
            ])
            ->abstract()
            ->public()
            ->tag('container.private', ['package' => 'symfony/framework-bundle', 'version' => '5.3'])
        ->set('workflow.marking_store.method', MethodMarkingStore::class)
            ->abstract()
        ->set('workflow.registry', Registry::class)
        ->alias(Registry::class, 'workflow.registry')
        ->set('workflow.security.expression_language', ExpressionLanguage::class)
    ;
};
