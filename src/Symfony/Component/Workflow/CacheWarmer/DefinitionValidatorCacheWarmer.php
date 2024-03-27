<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\CacheWarmer;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

final class DefinitionValidatorCacheWarmer implements CacheWarmerInterface
{
    /**
     * @param iterable<DefinitionAndValidator> $definitionAndValidators
     */
    public function __construct(
        private readonly iterable $definitionAndValidators,
    ) {
    }

    public function isOptional(): bool
    {
        return false;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        foreach ($this->definitionAndValidators as $definitionAndValidator) {
            $definitionAndValidator
                ->validator
                ->validate($definitionAndValidator->definition, $definitionAndValidator->name)
            ;
        }

        return [];
    }
}
