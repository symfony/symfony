<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Resource;

use Symfony\Component\Config\ResourceCheckerInterface;

class SkippingResourceChecker implements ResourceCheckerInterface
{
    private array $skippedResourceTypes;

    /**
     * @param class-string<ResourceInterface>[] $skippedResourceTypes
     */
    public function __construct(array $skippedResourceTypes = [])
    {
        $this->skippedResourceTypes = array_flip($skippedResourceTypes);
    }

    public function supports(ResourceInterface $metadata): bool
    {
        return !$this->skippedResourceTypes || isset($this->skippedResourceTypes[$metadata::class]);
    }

    public function isFresh(ResourceInterface $resource, int $timestamp): bool
    {
        return true;
    }
}
