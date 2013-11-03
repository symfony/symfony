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
class GreaterThan extends AbstractComparison
{
    const ERROR = '48f4b8a5-832c-4ffc-92b5-64dcd6312c32';

    public $message = 'This value should be greater than {{ compared_value }}.';
}
