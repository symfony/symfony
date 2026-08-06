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

/**
 * An unrelated view that Lead declares a class-level #[Map] to. Its only purpose is to make Lead
 * "carry metadata", so ObjectMapper reads metadata from the source side when mapping to LeadDto.
 */
class UnrelatedLeadView
{
    public int $id = 0;
}
