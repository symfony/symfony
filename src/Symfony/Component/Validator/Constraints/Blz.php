<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Markus Malkusch <markus@malkusch.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Value must be a German bank id (Bankleitzahl)
 *
 * This constraint depends on malkusch/bav.
 *
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Markus Malkusch <markus@malkusch.de>
 *
 * @see \malkusch\bav\BAV::isValidBank()
 * @see Bic
 * @see Konto
 * @api
 */
class Blz extends Constraint
{
    public $message = 'This value is not a valid German bank id (BLZ).';
}
