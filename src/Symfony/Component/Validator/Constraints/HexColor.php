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

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
final class HexColor extends Constraint
{
    public const INVALID_FORMAT_ERROR = 'e8c5955b-9ee3-451e-9c12-4d18240805db';

    /**
     * @see https://www.w3.org/TR/html52/sec-forms.html#color-state-typecolor
     */
    public $html5 = true;

    public $message = 'This value is not a valid hexadecimal color.';

    /**
     * {@inheritdoc}
     */
    public function getDefaultOption()
    {
        return 'html5';
    }
}
