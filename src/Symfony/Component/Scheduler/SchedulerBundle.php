<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleBundle;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Scheduler\Attribute\AsCronTask;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\DependencyInjection\AddScheduleMessengerPass;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Provides the scheduler services.
 */
#[RequiredBundle(ServicesBundle::class)]
#[RequiredBundle(ConsoleBundle::class, ignoreOnInvalid: true)]
class SchedulerBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new AddScheduleMessengerPass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->canBeDisabled()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            return;
        }

        $configurator->import('Resources/config/scheduler.php');

        if (!class_exists(Command::class)) {
            $container->removeDefinition('console.command.scheduler_debug');
        }

        if (!interface_exists(DenormalizerInterface::class)) {
            $container->removeDefinition('serializer.normalizer.scheduler_trigger');
        }

        $container->registerAttributeForAutoconfiguration(AsSchedule::class, static function (ChildDefinition $definition, AsSchedule $attribute): void {
            $definition->addTag('scheduler.schedule_provider', ['name' => $attribute->name]);
        });

        foreach ([AsPeriodicTask::class, AsCronTask::class] as $taskAttributeClass) {
            $container->registerAttributeForAutoconfiguration(
                $taskAttributeClass,
                static function (ChildDefinition $definition, AsPeriodicTask|AsCronTask $attribute, \ReflectionClass|\ReflectionMethod $reflector): void {
                    $tagAttributes = get_object_vars($attribute) + [
                        'trigger' => match (true) {
                            $attribute instanceof AsPeriodicTask => 'every',
                            $attribute instanceof AsCronTask => 'cron',
                        },
                    ];
                    if ($reflector instanceof \ReflectionMethod) {
                        if (isset($tagAttributes['method'])) {
                            throw new LogicException(\sprintf('"%s" attribute cannot declare a method on "%s::%s()".', $attribute::class, $reflector->class, $reflector->name));
                        }
                        $tagAttributes['method'] = $reflector->getName();
                    }
                    $definition->addTag('scheduler.task', $tagAttributes);
                }
            );
        }
    }
}
