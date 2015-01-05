<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Validator\Constraints\Deprecated;

trigger_error('Constant ERR_INVALID in class Symfony\Component\Form\Extension\Validator\Constraints\Form is deprecated since version 2.6 and will be removed in 3.0. Use NOT_SYNCHRONIZED_ERROR constant instead.', E_USER_DEPRECATED);

/**
 * @deprecated since version 2.7, to be removed in 3.0.
 * @internal
 */
final class Form
{
    const ERR_INVALID = 1;

    private function __construct()
    {
    }
}
