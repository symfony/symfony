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
 *
 * @api
 */
class Nif extends Constraint{
    public $message = 'This DNI/NIF doesn´t seem to be valid.';
}
