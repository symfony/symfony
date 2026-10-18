<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Attribute;

/**
 * Turns a method or an invokable class into a voter.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class AsVoter
{
    /**
     * @param string|list<string> $attributes The security attributes the voter votes on
     * @param int                 $priority   Voters with a higher priority vote first
     */
    public function __construct(
        public string|array $attributes,
        public int $priority = 0,
    ) {
    }
}
