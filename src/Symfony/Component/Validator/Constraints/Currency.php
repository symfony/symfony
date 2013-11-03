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
 * @author Miha Vrhovnik <miha.vrhovnik@pagein.si>
 *
 * @api
 */
class Currency extends Constraint
{
    const ERROR = 'f68a8d40-4231-4afd-83ac-0bcd818dc818';

    public $message = 'This value is not a valid currency.';
}
