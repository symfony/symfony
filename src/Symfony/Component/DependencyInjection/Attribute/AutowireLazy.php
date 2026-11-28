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

/**
 * Makes autowired service arguments lazy by default for a class.
 *
 * Individual arguments can opt out using #[Lazy(false)] or
 * #[Autowire(lazy: false)].
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AutowireLazy
{
}
