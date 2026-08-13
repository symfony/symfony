<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\RecursionCacheMultiTarget;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: SelfTarget::class)]
#[Map(target: SelfSummaryTarget::class)]
class SelfSource
{
    public int $id = 1;
    public ?self $self = null;
}
