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
 *
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
class Issn extends Constraint
{
    public $message = 'This value is not a valid ISSN.';
    public $caseSensitive = false;
    public $requireHyphen = false;
}
