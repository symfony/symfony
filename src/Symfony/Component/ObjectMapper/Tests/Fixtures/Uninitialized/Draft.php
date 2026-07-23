<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\Uninitialized;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: DraftView::class)]
final class Draft
{
    public string $title = 'hello';
    public ?string $summary;
}
