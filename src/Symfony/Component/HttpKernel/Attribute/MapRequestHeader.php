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

use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestHeaderValueResolver;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class MapRequestHeader extends ValueResolver
{
    public function __construct(
        public ?string $name = null,
        string $resolver = RequestHeaderValueResolver::class,
    ) {
        parent::__construct($resolver);
    }
}
