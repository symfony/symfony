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

enum CollectionMapperThrowPolicy: string
{
    /** Stop at first error. */
    case FAIL_EARLY = 'fail_early';

    /** Collect mapping errors, then throw. */
    case FAIL_SAFE = 'fail_safe';

    /** Continue processing, ignore mapping errors. */
    case IGNORE_MAPPING_ERRORS = 'ignore_mapping_errors';
}
