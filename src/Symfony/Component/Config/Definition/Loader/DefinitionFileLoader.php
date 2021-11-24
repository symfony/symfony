<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition\Loader;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * DefinitionFileLoader loads config definitions from a PHP file.
 *
 * The PHP file is required.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class DefinitionFileLoader extends FileLoader
{
    public function __construct(
        private TreeBuilder $treeBuilder,
        FileLocatorInterface $locator,
        private ?ContainerBuilder $container = null,
    ) {
        parent::__construct($locator);
    }

    /**
     * {@inheritdoc}
     */
    public function load(mixed $resource, string $type = null): mixed
    {
        // the loader variable is exposed to the included file below
        $loader = $this;

        $path = $this->locator->locate($resource);
        $this->setCurrentDir(\dirname($path));
        $this->container?->fileExists($path);

        // the closure forbids access to the private scope in the included file
        $load = \Closure::bind(static function ($file) use ($loader) {
            return include $file;
        }, null, ProtectedDefinitionFileLoader::class);

        $callback = $load($path);

        if (\is_object($callback) && \is_callable($callback)) {
            $this->executeCallback($callback, new DefinitionConfigurator($this->treeBuilder, $this, $path, $resource), $path);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(mixed $resource, string $type = null): bool
    {
        if (!\is_string($resource)) {
            return false;
        }

        if (null === $type && 'php' === pathinfo($resource, \PATHINFO_EXTENSION)) {
            return true;
        }

        return 'php' === $type;
    }

    private function executeCallback(callable $callback, DefinitionConfigurator $configurator, string $path): void
    {
        $callback = $callback(...);

        $arguments = [];
        $r = new \ReflectionFunction($callback);

        foreach ($r->getParameters() as $parameter) {
            $reflectionType = $parameter->getType();

            if (!$reflectionType instanceof \ReflectionNamedType) {
                throw new \InvalidArgumentException(sprintf('Could not resolve argument "$%s" for "%s". You must typehint it (for example with "%s").', $parameter->getName(), $path, DefinitionConfigurator::class));
            }

            $arguments[] = match ($reflectionType->getName()) {
                DefinitionConfigurator::class => $configurator,
                TreeBuilder::class => $this->treeBuilder,
                FileLoader::class, self::class => $this,
            };
        }

        $callback(...$arguments);
    }
}

/**
 * @internal
 */
final class ProtectedDefinitionFileLoader extends DefinitionFileLoader
{
}
