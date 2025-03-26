<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\MapStruct;

use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

#[Map(source: Source::class, target: Target::class)]
class AToBMapper implements ObjectMapperInterface
{
    public function __construct(private readonly ObjectMapper $objectMapper)
    {
    }

    // TODO: change attribute
    #[Map(source: 'propertyA', target: 'propertyD')]
    #[Map(source: 'propertyB', if: false)]
    public function map(object $source, object|string|null $target = null): object
    {
        return $this->objectMapper->map($source, $target);
    }
}
