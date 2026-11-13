<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Compatibility;

/**
 * @experimental
 */
enum SupportLevel: string
{
    /** Behaves as specified, within the limits of a cell grid */
    case Supported = 'supported';

    /** Some values or behaviors are supported; the note says which */
    case Partial = 'partial';

    /** Accepted and ignored: it has no meaning in a terminal */
    case Ignored = 'ignored';
}
