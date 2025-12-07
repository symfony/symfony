<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AccessControl\Attribute;

/**
 * @experimental
 */
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final readonly class AtLeastOneOf
{
    /**
     * @param list<AccessPolicy> $accessPolicies
     */
    public function __construct(
        public array $accessPolicies,
    ) {
    }
}
