<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\InferredFromPropertyType;

trait SelfTypedFriendTrait
{
    // PHP >= 8.5 resolves self to the class name at compile time, but not in traits
    public ?self $friend = null;
}
