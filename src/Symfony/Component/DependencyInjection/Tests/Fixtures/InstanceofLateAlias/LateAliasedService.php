<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\InstanceofLateAlias;

interface RealInterface
{
}

class LateAliasedService implements RealInterface
{
}

// "LateAliasedInterface" has no file of its own: it only becomes defined when
// this file is autoloaded, which lets tests exercise autoconfiguration rules
// registered for a type that is unloadable when the pass starts.
class_alias(RealInterface::class, __NAMESPACE__.'\LateAliasedInterface');
