<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMergeRead;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: Target::class)]
class NestedSource
{
    public string $label = 'outer';
    public ?Address $nullable = null;
    public Address|string|null $union = null;
    public ?self $child = null;
    public AddressInterface $iface;
    public $untyped = null;
    public ?object $object = null;
    public mixed $mixed = null;
    public ?AbstractAddress $abstract = null;
    public ?BaseAddress $base = null;
    public ?InterfaceMapped $viaInterface = null;
    public (Address&AddressInterface)|null $intersection = null;
}
