<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\UndefinedExtensionHandler;

require_once __DIR__.\DIRECTORY_SEPARATOR.'functions.php';

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ContainerConfigurator extends AbstractConfigurator
{
    public const FACTORY = 'container';

    private array $instanceof;
    private int $anonymousCount = 0;

    public function __construct(
        private ContainerBuilder $container,
        private PhpFileLoader $loader,
        array &$instanceof,
        private string $path,
        private string $file,
        private ?string $env = null,
    ) {
        $this->instanceof = &$instanceof;
    }

    final public function extension(string $namespace, array $config, bool $prepend = false): void
    {
        if ($prepend) {
            $this->container->prependExtensionConfig($namespace, static::processValue($config));

            return;
        }

        if (!$this->container->hasExtension($namespace)) {
            $extensions = array_filter(array_map(fn (ExtensionInterface $ext) => $ext->getAlias(), $this->container->getExtensions()));
            throw new InvalidArgumentException(UndefinedExtensionHandler::getErrorMessage($namespace, $this->file, $namespace, $extensions));
        }

        $this->container->loadFromExtension($namespace, static::processValue($config));
    }

    final public function import(string $resource, ?string $type = null, bool|string $ignoreErrors = false): void
    {
        $this->loader->setCurrentDir(\dirname($this->path));
        $this->loader->import($resource, $type, $ignoreErrors, $this->file);
    }

    final public function parameters(): ParametersConfigurator
    {
        return new ParametersConfigurator($this->container);
    }

    final public function services(): ServicesConfigurator
    {
        return new ServicesConfigurator($this->container, $this->loader, $this->instanceof, $this->path, $this->anonymousCount);
    }

    /**
     * Get the current environment to be able to write conditional configuration.
     */
    final public function env(): ?string
    {
        return $this->env;
    }

    final public function withPath(string $path): static
    {
        $clone = clone $this;
        $clone->path = $clone->file = $path;
        $clone->loader->setCurrentDir(\dirname($path));

        return $clone;
    }
}
