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
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Daniel Holmes <daniel@danielholmes.org>
 */
class NotIdenticalTo extends AbstractComparison
{
    public $message = 'This value should not be identical to {{ compared_value_type }} {{ compared_value }}.';
}
