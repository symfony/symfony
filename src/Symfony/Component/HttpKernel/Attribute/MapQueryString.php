<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Attribute;

use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestPayloadValueResolver;

/**
 * Controller parameter tag to map the query string of the request to typed object and validate it.
 *
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class MapQueryString extends ValueResolver
{
    public function __construct(
        public readonly array $context = [],
        string $resolver = RequestPayloadValueResolver::class,
    ) {
        parent::__construct($resolver);
    }
}
