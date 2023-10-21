<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Attribute;

trigger_deprecation('symfony/dependency-injection', '6.3', 'The "%s" class is deprecated, use "%s" instead.', MapDecorated::class, AutowireDecorated::class);

/**
 * @deprecated since Symfony 6.3, use AutowireDecorated instead
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class MapDecorated
{
}
