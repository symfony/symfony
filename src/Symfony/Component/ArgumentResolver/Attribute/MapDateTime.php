<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ArgumentResolver\Attribute;

use Symfony\Component\ArgumentResolver\ValueResolver\DateTimeValueResolver;
use Symfony\Component\ArgumentResolver\ValueResolver\ValueResolverInterface;

/**
 * Controller parameter tag to configure DateTime arguments.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class MapDateTime extends ValueResolver
{
    /**
     * @param string|null                                 $format   The DateTime format to use, @see https://php.net/datetime.format
     * @param bool                                        $disabled Whether this value resolver is disabled; this allows to enable a value resolver globally while disabling it in specific cases
     * @param class-string<ValueResolverInterface>|string $resolver The name of the resolver to use
     */
    public function __construct(
        public readonly ?string $format = null,
        bool $disabled = false,
        string $resolver = DateTimeValueResolver::class,
    ) {
        parent::__construct($resolver, $disabled);
    }
}
