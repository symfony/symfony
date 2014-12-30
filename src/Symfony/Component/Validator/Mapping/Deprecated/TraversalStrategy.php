<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping\Deprecated;

trigger_error('Constants STOP_RECURSION in class Symfony\Component\Validator\Mapping\TraversalStrategy is deprecated since version 2.3 and will be removed in 3.0.', E_USER_DEPRECATED);

/**
 * @deprecated since version 2.7, to be removed in 3.0.
 * @internal
 */
final class TraversalStrategy
{
    const STOP_RECURSION = 8;

    private function __construct()
    {
    }
}
