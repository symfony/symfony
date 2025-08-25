<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper;

final readonly class CollectionMapperThrowPolicy
{
    /** Stop at first error. */
    public const FAIL_EARLY = 'fail_early';

    /** Collect mapping errors, then throw. */
    public const FAIL_SAFE = 'fail_safe';

    /** Continue processing, ignore mapping errors. */
    public const IGNORE_MAPPING_ERRORS = 'ignore_mapping_errors';
}
