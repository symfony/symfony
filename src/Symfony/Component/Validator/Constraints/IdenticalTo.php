<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

/**
 * @Annotation
 *
 * @author Daniel Holmes <daniel@danielholmes.org>
 */
class IdenticalTo extends AbstractComparison
{
    const ERROR = '5368c23a-37c0-4148-83bc-7ee8ac34a1af';

    public $message = 'This value should be identical to {{ compared_value_type }} {{ compared_value }}.';
}
