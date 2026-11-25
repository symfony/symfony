<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Compatibility;

/**
 * @experimental
 */
enum SupportLevel: string
{
    /** Behaves as specified, within the documented implementation limits */
    case Supported = 'supported';
    /** Some values or behaviors are supported; the note says which */
    case Partial = 'partial';
    /** Accepted and ignored: it has no implemented rendering behavior */
    case Ignored = 'ignored';
    /** Not supported: what the matrix does not list, reported to the logger where a document uses it */
    case Unsupported = 'unsupported';
}
