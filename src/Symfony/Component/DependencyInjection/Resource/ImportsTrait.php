<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Resource;

trait ImportsTrait
{
    private array $imports = [];

    /** @var array<array{
     *     resource: string,
     *     type?: string|int|float|bool,
     *     ignoreErrors?: string|int|float|bool,
     * }|non-empty-string> $imports */
    public function imports(array $imports): static
    {
        $this->imports = $imports;

        return $this;
    }

    public function getImports(): array
    {
        return $this->imports;
    }
}
