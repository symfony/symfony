<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\Attribute\WhenClassExists;
use Symfony\Component\DependencyInjection\Attribute\WhenClassMissing;
use Symfony\Component\DependencyInjection\Attribute\WhenMissingService;
use Symfony\Component\DependencyInjection\Attribute\WhenParameter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;

/**
 * Evaluates the "when" conditions attached to definitions and excludes the definitions whose conditions don't match.
 *
 * The pass runs twice. Early in the "before optimization" phase, it evaluates pure conditions
 * (class existence, parameter values) so that rejected definitions never reach class resolution
 * or attribute autoconfiguration. At the start of the "optimization" phase, once every definition
 * source has contributed, it evaluates the remaining conditions; "missing service" conditions are
 * all evaluated in a single sweep against the resulting set of definitions, which makes their
 * outcome independent from the order in which definitions were registered.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class ResolveConditionalDefinitionsPass implements CompilerPassInterface
{
    /**
     * @param bool $pureConditionsOnly Whether to only evaluate the conditions that don't depend
     *                                 on the state of the container ("missing service" conditions
     *                                 are then kept for a later run of this pass)
     */
    public function __construct(
        private bool $pureConditionsOnly = false,
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        $conditionalIds = [];
        $missingServiceConditions = [];
        $rejected = [];

        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$this->pureConditionsOnly) {
                $this->assertNoInlineConditions($definition, $id, false);
            }

            if (!$conditions = $definition->getWhenConditions()) {
                continue;
            }

            $conditionalIds[] = $id;

            if ($definition->hasTag('container.excluded')) {
                // already rejected by an earlier run of this pass (or excluded for another reason);
                // re-reject it so that any behavior restored since (e.g. decoration read from
                // #[AsDecorator] by AutowireAsDecoratorPass) is stripped again
                $rejected[$id] = $definition->getTag('container.excluded')[0]['source'] ?? 'because of its "when" conditions';
                continue;
            }

            foreach ($conditions as $condition) {
                if ($condition instanceof WhenMissingService) {
                    $missingServiceConditions[$id][] = $condition;
                    continue;
                }

                if (isset($rejected[$id])) {
                    continue;
                }

                if (null !== $failure = $this->evaluate($condition, $id, $container)) {
                    $rejected[$id] = $failure;
                }
            }
        }

        if ($this->pureConditionsOnly) {
            foreach ($conditionalIds as $id) {
                if (isset($rejected[$id])) {
                    // conditions are kept so that the final run of this pass rejects the
                    // definition again after the attribute-reading passes have run
                    $this->reject($container, $id, $rejected[$id]);
                } else {
                    $container->getDefinition($id)->setWhenConditions($missingServiceConditions[$id] ?? []);
                }
            }
            $this->removeAliasesToRejectedServices($container, $rejected);

            return;
        }

        $stage1Rejected = $rejected;
        foreach ($missingServiceConditions as $id => $conditions) {
            if (isset($stage1Rejected[$id])) {
                continue;
            }

            foreach ($conditions as $condition) {
                $target = $this->resolveAliasTarget($container, $condition->id);

                if ($target === $id || isset($stage1Rejected[$target])) {
                    continue;
                }

                if (isset($missingServiceConditions[$target])) {
                    throw new LogicException(\sprintf('Invalid "when" condition on service "%s": it depends on the absence of service "%s", which itself uses a "missing_service" condition. Conditional services cannot depend on each other\'s absence.', $id, $condition->id));
                }

                if ($this->serviceExists($container, $target)) {
                    $rejected[$id] = \sprintf('because the "%s" service is already defined', $condition->id);
                    break;
                }
            }
        }

        foreach ($conditionalIds as $id) {
            if (isset($rejected[$id])) {
                $this->reject($container, $id, $rejected[$id]);
            }
            $container->getDefinition($id)->setWhenConditions([]);
        }

        $this->removeAliasesToRejectedServices($container, $rejected);
    }

    private function evaluate(object $condition, string $id, ContainerBuilder $container): ?string
    {
        if ($condition instanceof WhenClassExists || $condition instanceof WhenClassMissing) {
            $exists = null !== $container->getReflectionClass($condition->class, false);

            if ($exists && $condition->package) {
                $exists = ContainerBuilder::willBeAvailable($condition->package, $condition->class, $condition->parentPackages);
            }

            if ($condition instanceof WhenClassMissing) {
                return $exists ? \sprintf('because the "%s" class%s is available', $condition->class, $condition->package ? \sprintf(' from package "%s"', $condition->package) : '') : null;
            }

            return $exists ? null : \sprintf('because the "%s" class%s is not available', $condition->class, $condition->package ? \sprintf(' from package "%s"', $condition->package) : '');
        }

        if ($condition instanceof WhenParameter) {
            if (!$container->hasParameter($condition->name)) {
                return \sprintf('because the "%s" parameter is not defined', $condition->name);
            }

            $bag = $container->getParameterBag();
            $value = $bag->resolveValue($container->getParameter($condition->name));
            $expected = $bag->resolveValue($condition->value);

            if ($bag instanceof EnvPlaceholderParameterBag && str_contains(serialize([$value, $expected]), $bag->getEnvPlaceholderUniquePrefix())) {
                throw new LogicException(\sprintf('The "when" condition of service "%s" uses parameter "%s", which references an environment variable; its value is not known at compile time. Use a build-time parameter instead, or register the service conditionally in a compiler pass.', $id, $condition->name));
            }

            return $value === $expected ? null : \sprintf('because the "%s" parameter does not have the expected value', $condition->name);
        }

        throw new InvalidArgumentException(\sprintf('Unsupported "when" condition of type "%s" on service "%s".', get_debug_type($condition), $id));
    }

    private function reject(ContainerBuilder $container, string $id, string $reason): void
    {
        $definition = $container->getDefinition($id);

        if (null === $definition->getClass()) {
            // prevent ResolveClassPass from failing on FQCN-looking ids whose class doesn't exist
            $definition->setClass($id);
        }

        $definition->setAbstract(true)
            ->setDecoratedService(null)
            ->setTags(['container.excluded' => [['source' => $reason]]]);
    }

    private function assertNoInlineConditions(mixed $value, string $id, bool $isInline): void
    {
        if (\is_array($value)) {
            foreach ($value as $v) {
                $this->assertNoInlineConditions($v, $id, $isInline);
            }
        } elseif ($value instanceof ArgumentInterface) {
            $this->assertNoInlineConditions($value->getValues(), $id, $isInline);
        } elseif ($value instanceof Definition) {
            if ($isInline && $value->getWhenConditions()) {
                throw new LogicException(\sprintf('Invalid inline definition used in service "%s": "when" conditions are only supported on definitions registered in the container.', $id));
            }

            $this->assertNoInlineConditions($value->getArguments(), $id, true);
            $this->assertNoInlineConditions($value->getProperties(), $id, true);
            $this->assertNoInlineConditions($value->getFactory(), $id, true);
            $this->assertNoInlineConditions($value->getConfigurator(), $id, true);
            $this->assertNoInlineConditions($value->getBindings(), $id, true);
            foreach ($value->getMethodCalls() as $call) {
                $this->assertNoInlineConditions($call[1] ?? [], $id, true);
            }
        }
    }

    private function removeAliasesToRejectedServices(ContainerBuilder $container, array $rejected): void
    {
        if (!$rejected) {
            return;
        }

        $removedAliases = [];
        foreach ($container->getAliases() as $aliasId => $alias) {
            if (isset($rejected[$this->resolveAliasTarget($container, (string) $alias)])) {
                $removedAliases[] = $aliasId;
            }
        }
        foreach ($removedAliases as $aliasId) {
            $container->removeAlias($aliasId);
        }
    }

    private function serviceExists(ContainerBuilder $container, string $id): bool
    {
        if ($container->hasDefinition($id)) {
            $definition = $container->getDefinition($id);

            return !$definition->isAbstract() && !$definition->hasTag('container.excluded');
        }

        // services set at runtime with ContainerBuilder::set() have no definition
        return $container->has($id);
    }

    private function resolveAliasTarget(ContainerBuilder $container, string $id): string
    {
        $seen = [];
        while ($container->hasAlias($id) && !isset($seen[$id])) {
            $seen[$id] = true;
            $id = (string) $container->getAlias($id);
        }

        return $id;
    }
}
