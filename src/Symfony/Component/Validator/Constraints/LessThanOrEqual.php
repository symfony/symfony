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
 *
 * @deprecated Deprecated as of Symfony 2.6, to be removed in version 3.0.
 *             Use {@link Expression} instead.
 */
class LessThanOrEqual extends AbstractComparison
{
    public $message = 'This value should be less than or equal to {{ compared_value }}.';
}
