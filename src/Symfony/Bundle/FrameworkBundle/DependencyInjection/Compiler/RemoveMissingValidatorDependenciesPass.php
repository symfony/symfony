<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\Console\EventListener\ValidateQuestionInputListener;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Drops the services that need the validator provided by ValidationBundle.
 *
 * @internal
 */
class RemoveMissingValidatorDependenciesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('validator')) {
            $container->removeDefinition('console.command.validator_debug');
            $container->removeDefinition('.console.validate_question_input_listener');
        } elseif (!class_exists(ValidateQuestionInputListener::class)) {
            $container->removeDefinition('.console.validate_question_input_listener');
        }
    }
}
