<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo;

use PHPStan\PhpDocParser\Parser\PhpDocParser;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\TypeInfo\TypeResolver\PhpDocAwareReflectionTypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;

/**
 * Provides the type resolving services.
 */
#[RequiredBundle(ServicesBundle::class)]
class TypeInfoBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $refinements = implode(', ', array_map(static fn (Refinement $refinement): string => '"'.$refinement->value.'"', Refinement::cases()));

        $definition->rootNode()
            ->canBeDisabled()
            ->children()
                ->arrayNode('aliases', 'alias')
                    ->info('Additional type aliases to be used during type context creation.')
                    ->defaultValue([])
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name')
                    ->scalarPrototype()->end()
                ->end()
                ->variableNode('refinements')
                    ->info(\sprintf('The PHPDoc refinements to resolve instead of their builtin type (%s), true for all of them, including those added later, or false for none. Not setting it is deprecated since Symfony 8.2, all refinements will be resolved by default as of 9.0.', $refinements))
                    ->defaultNull()
                    ->validate()
                        ->ifTrue(static fn (mixed $refinements): bool => null !== $refinements && !\is_bool($refinements) && (!\is_array($refinements) || !array_is_list($refinements) || array_any($refinements, static fn (mixed $refinement): bool => !$refinement instanceof Refinement && (!\is_string($refinement) || null === Refinement::tryFrom($refinement)))))
                        ->thenInvalid(\sprintf('The "refinements" option must be a boolean or a list of %s, %%s given.', $refinements))
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            return;
        }

        $configurator->import('Resources/config/type_info.php');

        if (ContainerBuilder::willBeAvailable('phpstan/phpdoc-parser', PhpDocParser::class, ['symfony/framework-bundle', 'symfony/type-info'])) {
            // refinements only come from PHPDoc types
            if (null === $config['refinements']) {
                trigger_deprecation('symfony/type-info', '8.2', 'Not setting the "type_info.refinements" option is deprecated; all PHPDoc refinements will be resolved by default in 9.0. Set it to true to resolve them now, or to false to keep their builtin types.');
            }

            $refinements = \is_array($config['refinements']) ? array_map(static fn (Refinement|string $refinement): Refinement => $refinement instanceof Refinement ? $refinement : Refinement::from($refinement), $config['refinements']) : $config['refinements'] ?? false;

            $container->register('type_info.resolver.string', StringTypeResolver::class)
                ->setArguments([null, null, $config['aliases'], $refinements]);

            $container->register('type_info.resolver.reflection_parameter.phpdoc_aware', PhpDocAwareReflectionTypeResolver::class)
                ->setArguments([new Reference('type_info.resolver.reflection_parameter'), new Reference('type_info.resolver.string'), new Reference('type_info.type_context_factory')]);
            $container->register('type_info.resolver.reflection_property.phpdoc_aware', PhpDocAwareReflectionTypeResolver::class)
                ->setArguments([new Reference('type_info.resolver.reflection_property'), new Reference('type_info.resolver.string'), new Reference('type_info.type_context_factory')]);
            $container->register('type_info.resolver.reflection_return.phpdoc_aware', PhpDocAwareReflectionTypeResolver::class)
                ->setArguments([new Reference('type_info.resolver.reflection_return'), new Reference('type_info.resolver.string'), new Reference('type_info.type_context_factory')]);

            /** @var ServiceLocatorArgument $resolversLocator */
            $resolversLocator = $container->getDefinition('type_info.resolver')->getArgument(0);
            $resolversLocator->setValues([
                'string' => new Reference('type_info.resolver.string'),
                \ReflectionParameter::class => new Reference('type_info.resolver.reflection_parameter.phpdoc_aware'),
                \ReflectionProperty::class => new Reference('type_info.resolver.reflection_property.phpdoc_aware'),
                \ReflectionFunctionAbstract::class => new Reference('type_info.resolver.reflection_return.phpdoc_aware'),
            ] + $resolversLocator->getValues());

            $container->getDefinition('type_info.type_context_factory')->replaceArgument(1, $config['aliases']);
        }
    }
}
