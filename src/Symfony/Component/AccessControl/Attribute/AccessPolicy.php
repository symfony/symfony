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
readonly class AccessPolicy
{
    /**
     * @param array<array-key, mixed> $metadata
     */
    public function __construct(
        public mixed $attribute,
        public mixed $subject = null,
        public ?string $strategy = null,
        public array $metadata = [],
        public bool $allowIfAllAbstain = false,
        public string $message = 'Access Denied.',
        public ?int $statusCode = null,
        public ?int $exceptionCode = null,
    ) {
    }
}
