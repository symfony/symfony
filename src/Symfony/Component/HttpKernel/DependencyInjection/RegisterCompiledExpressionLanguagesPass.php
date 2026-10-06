<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DependencyInjection;

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\CompiledExpressionLanguage;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Decorates the expression languages tagged "expression_language.compiled" with a CompiledExpressionLanguage, which loads the expressions compiled when warming up the cache outside of debug mode.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class RegisterCompiledExpressionLanguagesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('expression_language.cache_warmer')) {
            return;
        }

        if (!class_exists(CompiledExpressionLanguage::class)) {
            $container->removeDefinition('expression_language.cache_warmer');

            return;
        }

        $debug = $container->hasParameter('kernel.debug') && $container->getParameter('kernel.debug');
        $expressionLanguages = $attributes = $expressions = [];

        foreach ($container->findTaggedServiceIds('expression_language.compiled') as $id => $tags) {
            if (!$r = $container->getReflectionClass($class = self::getClass($container, $id), false)) {
                throw new InvalidArgumentException(\sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
            }

            if (!is_a($r->name, ExpressionLanguage::class, true)) {
                throw new InvalidArgumentException(\sprintf('Service "%s" tagged "expression_language.compiled" must be an instance of "%s".', $id, ExpressionLanguage::class));
            }

            // decorating in debug mode too keeps the class of the service the same in every environment
            $file = $debug ? null : '%kernel.build_dir%/expression_language/'.(preg_match('/^[\w.]++$/', $id) ? $id : ContainerBuilder::hash($id)).'.php';
            $container->register('.'.$id.'.compiled', CompiledExpressionLanguage::class)
                ->setDecoratedService($id)
                ->setArguments([new Reference('.inner'), $file])
                ->setLazy($container->getDefinition($id)->isLazy());

            if ($debug) {
                continue;
            }

            $expressionLanguages[$file] = new Reference($id);

            foreach ($tags as $tag) {
                foreach ($tag['attributes'] ?? [] as $class => $properties) {
                    foreach ($properties as $property) {
                        $attributes[$file][$class][$property] ??= false;
                    }
                }

                foreach ($tag['string_expressions'] ?? [] as $class => $properties) {
                    foreach ($properties as $property) {
                        $attributes[$file][$class][$property] = true;
                    }
                }

                // an expression listed by several tags can read the variables of all of them, or any variable when one of them doesn't list its variables
                $variables = $tag['variables'] ?? null;
                foreach ($tag['expressions'] ?? [] as $expression) {
                    if (!\array_key_exists($expression, $expressions[$file] ?? [])) {
                        $expressions[$file][$expression] = $variables;
                    } elseif (null === $variables || null === $expressions[$file][$expression]) {
                        $expressions[$file][$expression] = null;
                    } else {
                        $expressions[$file][$expression] = array_values(array_unique([...$expressions[$file][$expression], ...$variables]));
                    }
                }
            }
        }

        if (!$expressionLanguages) {
            $container->removeDefinition('expression_language.cache_warmer');

            return;
        }

        $controllers = [];
        foreach ($container->findTaggedServiceIds('controller.service_arguments') as $id => $tags) {
            if ($class = self::getClass($container, $id)) {
                $controllers[] = $class;
            }
        }

        $container->getDefinition('expression_language.cache_warmer')
            ->replaceArgument(0, new IteratorArgument($expressionLanguages))
            ->replaceArgument(1, array_values(array_unique($controllers)))
            ->replaceArgument(2, $attributes)
            ->replaceArgument(3, $expressions);
    }

    private static function getClass(ContainerBuilder $container, string $id): ?string
    {
        $definition = $container->getDefinition($id);
        $class = $definition->getClass();

        while ($definition instanceof ChildDefinition) {
            $definition = $container->findDefinition($definition->getParent());
            $class ??= $definition->getClass();
        }

        return $container->getParameterBag()->resolveValue($class);
    }
}
