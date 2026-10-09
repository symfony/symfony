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
 * Decorates the expression languages tagged "expression_language.compiled" with a CompiledExpressionLanguage, which loads the expressions compiled when warming up the cache outside of debug mode, and tells the collector of their expressions which ones they evaluate.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class RegisterCompiledExpressionLanguagesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('expression_language.collector') || !class_exists(CompiledExpressionLanguage::class)) {
            self::removeServices($container);

            return;
        }

        $debug = $container->hasParameter('kernel.debug') && $container->getParameter('kernel.debug');
        $expressionLanguages = $files = $attributes = $expressions = [];

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

            $expressionLanguages[$id] = new Reference($id);

            if (!$debug) {
                $files[$id] = $file;
            }

            foreach ($tags as $tag) {
                $variables = $tag['variables'] ?? null;

                foreach ($tag['attributes'] ?? [] as $class => $properties) {
                    foreach ($properties as $property) {
                        [$stringsAreExpressions, $boundVariables] = $attributes[$id][$class][$property] ?? [false, $variables];
                        $attributes[$id][$class][$property] = [$stringsAreExpressions, self::mergeVariables($boundVariables, $variables)];
                    }
                }

                foreach ($tag['string_expressions'] ?? [] as $class => $properties) {
                    foreach ($properties as $property) {
                        $boundVariables = ($attributes[$id][$class][$property] ?? [true, $variables])[1];
                        $attributes[$id][$class][$property] = [true, self::mergeVariables($boundVariables, $variables)];
                    }
                }

                foreach ($tag['expressions'] ?? [] as $expression) {
                    [$listedVariables, $sources] = $expressions[$id][$expression] ?? [$variables, []];

                    if (isset($tag['source'])) {
                        $sources[] = $tag['source'];
                    }

                    $expressions[$id][$expression] = [self::mergeVariables($listedVariables, $variables), $sources];
                }
            }
        }

        if (!$expressionLanguages) {
            self::removeServices($container);

            return;
        }

        $controllers = [];
        foreach ($container->findTaggedServiceIds('controller.service_arguments') as $id => $tags) {
            if ($class = self::getClass($container, $id)) {
                $controllers[] = $class;
            }
        }

        $container->getDefinition('expression_language.collector')
            ->replaceArgument(0, array_values(array_unique($controllers)))
            ->replaceArgument(1, $attributes)
            ->replaceArgument(2, $expressions);

        if (!$debug && $container->hasDefinition('expression_language.cache_warmer')) {
            $container->getDefinition('expression_language.cache_warmer')
                ->replaceArgument(0, new IteratorArgument($expressionLanguages))
                ->replaceArgument(1, $files);
        } else {
            $container->removeDefinition('expression_language.cache_warmer');
        }

        if ($container->hasDefinition('console.command.expression_lint')) {
            $container->getDefinition('console.command.expression_lint')
                ->replaceArgument(0, new IteratorArgument($expressionLanguages));
        }
    }

    /**
     * An expression bound or listed by several tags can read the variables of all of them, or any variable when one of them doesn't list its variables.
     */
    private static function mergeVariables(?array $variables, ?array $moreVariables): ?array
    {
        if (null === $variables || null === $moreVariables) {
            return null;
        }

        return array_values(array_unique([...$variables, ...$moreVariables]));
    }

    private static function removeServices(ContainerBuilder $container): void
    {
        $container->removeDefinition('expression_language.collector');
        $container->removeDefinition('expression_language.cache_warmer');
        $container->removeDefinition('console.command.expression_lint');
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
