<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Attribute;

use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\Security\Http\Controller\UserValueResolver;

/**
 * Indicates that a controller argument should receive the current logged user.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class CurrentUser extends ValueResolver
{
    /**
     * @param bool   $disabled Whether this value resolver is disabled, which allows to enable a value resolver globally while disabling it in specific cases
     * @param string $resolver The class name of the resolver to use
     */
    public function __construct(bool $disabled = false, string $resolver = UserValueResolver::class)
    {
        parent::__construct($resolver, $disabled);
    }
}
