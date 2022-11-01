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

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class ComposerConfigurator
{
    /**
     * @var array<class-string>
     */
    private array $attributes = [];

    /**
     * @var array<class-string>
     */
    private array $instanceOf = [];

    /**
     * @var array<PrototypeConfigurator>
     */
    private array $prototypes = [];

    /**
     * @throws \JsonException
     */
    public function __construct(
        ServicesConfigurator $parent,
        PhpFileLoader $loader,
        Definition $defaults,
        string $composerFile,
        bool $allowParent,
        string $path,
    ) {
        $composerFileAbsolutePath = \dirname($path).'/'.$composerFile;
        if (!file_exists($composerFileAbsolutePath) || !is_readable($composerFileAbsolutePath)) {
            throw new \InvalidArgumentException(sprintf('File "%s" not found.', $composerFileAbsolutePath));
        }

        $namespaces = json_decode(file_get_contents($composerFileAbsolutePath), true, 512, \JSON_THROW_ON_ERROR)['autoload']['psr-4'] ?? [];
        if (!\is_array($namespaces)) {
            throw new \InvalidArgumentException('Autoload PSR-4 mapping not found.');
        }

        $composerDir = \dirname($composerFile);
        foreach ($namespaces as $namespace => $directory) {
            $resource = $composerDir.'/'.$directory;
            $this->prototypes[] = new PrototypeConfigurator(
                $parent,
                $loader,
                $defaults,
                $namespace,
                $resource,
                $allowParent,
                $path
            );
        }
    }

    public function __destruct()
    {
        $classFilter = $this->buildFilter();

        if ($classFilter) {
            foreach ($this->prototypes as $prototype) {
                $prototype->classFilter($classFilter);
            }
        }

        unset($this->prototypes);
    }

    /**
     * @param class-string $attribute
     *
     * @return $this
     */
    public function hasAttribute(string $attribute): static
    {
        $this->attributes[] = $attribute;

        return $this;
    }

    /**
     * @param class-string $class
     *
     * @return $this
     */
    public function instanceOf(string $class): static
    {
        $this->instanceOf[] = $class;

        return $this;
    }

    /**
     * @return (callable(\ReflectionClass): bool)|null
     */
    private function buildFilter(): ?callable
    {
        if (empty($this->attributes) && empty($this->instanceOf)) {
            return null;
        }

        return function (\ReflectionClass $r): bool {
            foreach ($this->attributes as $attribute) {
                if (\count($r->getAttributes($attribute, \ReflectionAttribute::IS_INSTANCEOF)) > 0) {
                    return true;
                }
            }

            foreach ($this->instanceOf as $class) {
                if (is_subclass_of($r->name, $class, true)) {
                    return true;
                }
            }

            return false;
        };
    }
}
