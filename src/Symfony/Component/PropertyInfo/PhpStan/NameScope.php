<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\PropertyInfo\PhpStan;

/**
 * NameScope class adapted from PHPStan code.
 *
 * @copyright Copyright (c) 2016, PHPStan https://github.com/phpstan/phpstan-src
 * @copyright Copyright (c) 2016, Ondřej Mirtes
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
class NameScope
{
    private $namespace;
    /** @var array<string, string> alias(string) => fullName(string) */
    private $uses;
    private $className;

    public function __construct(string $fullClassName)
    {
        $path = explode('\\', $fullClassName);
        $className = array_pop($path);
        $namespace = str_replace('\\'.$className, '', $fullClassName);
        $uses = []; // @fixme

        $this->namespace = $namespace;
        $this->uses = $uses;
        $this->className = $className;
    }

    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    /**
     * @return array<string, string>
     */
    public function getUses(): array
    {
        return $this->uses;
    }

    public function hasUseAlias(string $name): bool
    {
        return \array_key_exists(mb_strtolower($name), $this->uses);
    }

    public function getClassName(): ?string
    {
        return $this->className;
    }

    public function resolveStringName(string $name): string
    {
        if (0 === strpos($name, '\\')) {
            return ltrim($name, '\\');
        }

        $nameParts = explode('\\', $name);
        $firstNamePart = mb_strtolower($nameParts[0]);
        if (isset($this->uses[$firstNamePart])) {
            if (1 === \count($nameParts)) {
                return $this->uses[$firstNamePart];
            }
            array_shift($nameParts);

            return sprintf('%s\\%s', $this->uses[$firstNamePart], implode('\\', $nameParts));
        }

        if (null !== $this->namespace) {
            return sprintf('%s\\%s', $this->namespace, $name);
        }

        return $name;
    }

    public function resolveRootClass(): string
    {
        return $this->resolveStringName($this->getClassName());
    }
}
