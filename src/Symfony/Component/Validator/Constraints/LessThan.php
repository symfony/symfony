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
class LessThan extends AbstractComparison
{
    const ERROR = '16a4f865-0060-40e9-9d60-8ab113bfb293';

    public $message = 'This value should be less than {{ compared_value }}.';
}
