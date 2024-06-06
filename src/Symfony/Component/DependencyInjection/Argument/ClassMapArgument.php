<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Argument;

final class ClassMapArgument implements ArgumentInterface
{
    public string $namespace;

    /**
     * @param class-string|null $instanceOf
     * @param class-string|null $withAttribute
     */
    public function __construct(
        string $namespace,
        public string $path,
        public ?string $instanceOf = null,
        public ?string $withAttribute = null,
        public ?string $indexBy = null,
    ) {
        $this->setNamespace($namespace);
    }

    public function getValues(): array
    {
        return [
            'namespace' => $this->namespace,
            'path' => $this->path,
            'instance_of' => $this->instanceOf,
            'with_attribute' => $this->withAttribute,
            'index_by' => $this->indexBy,
        ];
    }

    public function setValues(array $values): void
    {
        [
            'namespace' => $namespace,
            'path' => $this->path,
            'instance_of' => $this->instanceOf,
            'with_attribute' => $this->withAttribute,
            'index_by' => $this->indexBy,
        ] = $values;

        $this->setNamespace($namespace);
    }

    private function setNamespace(string $namespace): void
    {
        $this->namespace = ltrim($namespace, '\\').('\\' !== $namespace[-1] ? '\\' : '');
    }
}
