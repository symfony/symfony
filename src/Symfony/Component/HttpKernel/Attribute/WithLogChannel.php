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

/**
 * Defines the log channel applied to an exception.
 * 
 * @author Arkalo <symfony@arkalo.ovh>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class WithLogChannel
{
    public function __construct(public readonly string $channel) {}
}
