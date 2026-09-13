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
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ResolveEnvPlaceholdersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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

        $bag = $container->getParameterBag();
        $envVars = array_keys($container->getEnvCounters());
        $dump = $dumpParameters = null;
        $readAtRuntime = null;

        try {
            $dump = new ContainerBuilder($bag);
            $dump->setDefinitions($container->getDefinitions());
            $dump->setAliases($container->getAliases());

            if ($bag instanceof EnvPlaceholderParameterBag) {
                $dump = DeepCloner::deepClone($dump);
                (new ResolveEnvPlaceholdersPass(null))->process($dump);

                // resolving the placeholders of a copy reports the variables the container still
                // reads when it runs, which the bag cannot tell: it counts a variable as read when
                // the processed configuration holds it, even when no definition does
                $usedInParameters = [];
                $dumpParameters = $container->resolveEnvPlaceholders($this->escapeParameters($bag->all()), null, $usedInParameters);
                $readAtRuntime = array_unique(array_merge(array_keys(array_filter($dump->getEnvCounters())), array_keys($usedInParameters)));
            }
        } catch (\Throwable $e) {
            $container->getCompiler()->log($this, $e->getMessage());
            $dump = null;
        }

        // the compiler knows which variables are referenced; the dumps below cannot be asked,
        // since the serialized one has its placeholders already resolved
        $container->setParameter('.debug.container.env_vars', $envVars);

        // the ones nothing reads at runtime were read while the container was compiled, so a new
        // value only applies once it is rebuilt
        $readAtRuntime ??= $bag instanceof EnvPlaceholderParameterBag ? array_keys($bag->getEnvPlaceholders()) : $envVars;
        $container->setParameter('.debug.container.inlined_env_vars', array_values(array_diff($envVars, $readAtRuntime)));

        $file = $container->getParameter('debug.container.dump');
        $cache = new ConfigCache($file, true);
        if ($cache->isFresh()) {
            return;
        }
        $cache->write((new XmlDumper($container))->dump(), $container->getResources());

        if (!str_ends_with($file, '.xml') || null === $dump) {
            return;
        }

        $file = substr_replace($file, '.ser', -4);

        try {
            if (null !== $dumpParameters) {
                $dump->__construct(new EnvPlaceholderParameterBag($dumpParameters));
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
}
