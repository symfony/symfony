<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\SourceCarriesMetadata;

use Symfony\Component\ObjectMapper\Attribute\Map;

/**
 * Reached only through a transform or an explicit map($lead, LeadDto::class), so it is never itself
 * referenced by a class-level #[Map]. Its $type property declares its own transform, which must be
 * honored even when the source carries metadata.
 */
final class LeadDto
{
    public function __construct(
        public int $id,
        #[Map(source: 'type', transform: [ToTypeDto::class, 'transform'])]
        public TypeDto $type,
    ) {
    }
}
