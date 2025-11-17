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
            $dump = new ContainerBuilder(clone $container->getParameterBag());
            $dump->setDefinitions(unserialize(serialize($container->getDefinitions())));
            $dump->setAliases($container->getAliases());

            if (($bag = $container->getParameterBag()) instanceof EnvPlaceholderParameterBag) {
                (new ResolveEnvPlaceholdersPass(null))->process($dump);
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
