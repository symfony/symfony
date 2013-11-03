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
class NotIdenticalTo extends AbstractComparison
{
    const ERROR = '2c96e376-8e6f-488c-9936-840a73fd3163';

    public $message = 'This value should not be identical to {{ compared_value_type }} {{ compared_value }}.';
}
