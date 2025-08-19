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
    const FAIL_EARLY = 'fail_early';

    /** Collect mapping errors, then throw. */
    const FAIL_SAFE = 'fail_safe';

    /** Continue processing, ignore mapping errors. */
    const IGNORE_MAPPING_ERRORS = 'ignore_mapping_errors';
}
