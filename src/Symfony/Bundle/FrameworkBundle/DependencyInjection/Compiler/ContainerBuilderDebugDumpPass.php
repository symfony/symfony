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

use Symfony\Component\Config\ConfigCache;
use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ResolveEnvPlaceholdersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Dumper\XmlDumper;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\VarExporter\DeepCloner;

/**
 * Dumps the ContainerBuilder to a cache file so that it can be used by
 * debugging tools such as the debug:container console command.
 *
 * @author Ryan Weaver <ryan@thatsquality.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ContainerBuilderDebugDumpPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->getParameter('debug.container.dump')) {
            return;
        }

        // the compiler knows which variables are referenced; the dumps below cannot be asked,
        // since the serialized one has its placeholders already resolved
        $container->setParameter('.debug.container.env_vars', array_keys($container->getEnvCounters()));

        $file = $container->getParameter('debug.container.dump');
        $cache = new ConfigCache($file, true);
        if ($cache->isFresh()) {
            return;
        }
        $cache->write((new XmlDumper($container))->dump(), $container->getResources());

        if (!str_ends_with($file, '.xml')) {
            return;
        }

        $file = substr_replace($file, '.ser', -4);

        try {
            $bag = $container->getParameterBag();
            $dump = new ContainerBuilder($bag);
            $dump->setDefinitions($container->getDefinitions());
            $dump->setAliases($container->getAliases());

            if ($bag instanceof EnvPlaceholderParameterBag) {
                if (!$this->resolveEnvPlaceholdersOnCopies($dump)) {
                    $dump = DeepCloner::deepClone($dump);
                    (new ResolveEnvPlaceholdersPass(null))->process($dump);
                }
                $dump->__construct(new EnvPlaceholderParameterBag($container->resolveEnvPlaceholders($this->escapeParameters($bag->all()))));
            }

            $fs = new Filesystem();
            $fs->dumpFile($file, serialize($dump));
            $fs->chmod($file, 0o666, umask());
        } catch (\Throwable $e) {
            $container->getCompiler()->log($this, $e->getMessage());
            // ignore serialization and file-system errors
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }

    private function resolveEnvPlaceholdersOnCopies(ContainerBuilder $container): bool
    {
        // the pass changes definitions and arguments in place, so it gets copies of them, kept only when they changed
        $pass = new class(null) extends ResolveEnvPlaceholdersPass {
            public \WeakMap $resolved;
            public array $unprocessed = [];

            public function resolve(ContainerBuilder $container): array
            {
                $this->container = $container;
                $this->resolved = new \WeakMap();

                try {
                    return $this->processValue($container->getDefinitions(), true);
                } finally {
                    $this->container = null;
                }
            }

            protected function processValue(mixed $value, bool $isRoot = false): mixed
            {
                if (!$value instanceof Definition && !$value instanceof ArgumentInterface) {
                    return parent::processValue($value, $isRoot);
                }

                if (!isset($this->resolved[$value])) {
                    if ($value instanceof Definition && ($value->getBindings() || $value->getInstanceofConditionals())) {
                        $this->unprocessed[] = [$value->getBindings(), $value->getInstanceofConditionals()];
                    }
                    parent::processValue($clone = clone $value, $isRoot);
                    $this->resolved[$value] = (array) $clone === (array) $value ? $value : $clone;
                }

                return $this->resolved[$value];
            }
        };

        $definitions = $pass->resolve($container);

        foreach ($container->getDefinitions() as $definition) {
            if ($definition->hasTag('container.excluded')) {
                $pass->unprocessed[] = $definition;
            }
        }

        // bindings, instanceof conditionals and excluded definitions are not processed: when they hold an object that got a copy, they need a full clone
        if (self::holdsCopiedObject($pass->unprocessed, $pass->resolved, new \WeakMap())) {
            return false;
        }

        $container->setDefinitions($definitions);

        return true;
    }

    private function escapeParameters(array $parameters): array
    {
        $params = [];
        foreach ($parameters as $k => $v) {
            $params[$k] = match (true) {
                \is_array($v) => $this->escapeParameters($v),
                \is_string($v) => str_replace('%', '%%', $v),
                default => $v,
            };
        }

        return $params;
    }

    private static function holdsCopiedObject(mixed $value, \WeakMap $resolved, \WeakMap $seen): bool
    {
        if (\is_array($value)) {
            foreach ($value as $v) {
                if (self::holdsCopiedObject($v, $resolved, $seen)) {
                    return true;
                }
            }

            return false;
        }

        if ((!$value instanceof Definition && !$value instanceof ArgumentInterface) || isset($seen[$value])) {
            return false;
        }
        $seen[$value] = true;

        if (($resolved[$value] ?? $value) !== $value) {
            return true;
        }

        if ($value instanceof ArgumentInterface) {
            return self::holdsCopiedObject($value->getValues(), $resolved, $seen);
        }

        return self::holdsCopiedObject([$value->getArguments(), $value->getProperties(), $value->getMethodCalls(), $value->getFactory(), $value->getConfigurator(), $value->getBindings(), $value->getInstanceofConditionals()], $resolved, $seen);
    }
}
