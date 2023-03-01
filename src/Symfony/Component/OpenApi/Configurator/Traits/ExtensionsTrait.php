<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Configurator\Traits;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
trait ExtensionsTrait
{
    /**
     * @var array<string, mixed>
     */
    private array $specificationExtensions = [];

    public function specificationExtension(string $name, mixed $value): static
    {
        $this->specificationExtensions[$name] = $value;

        return $this;
    }
}
