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

use Symfony\Component\HttpKernel\Controller\ArgumentResolver\SessionParameterValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;

/**
 * Can be used to pass a session parameter to a controller argument.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class MapSessionParameter extends ValueResolver
{
    /**
     * @param string|null                                 $name     The name of the session parameter; if null, the name of the argument in the controller will be used
     * @param class-string<ValueResolverInterface>|string $resolver The name of the resolver to use
     */
    public function __construct(
        public ?string $name = null,
        string $resolver = SessionParameterValueResolver::class,
    ) {
        parent::__construct($resolver);
    }
}
