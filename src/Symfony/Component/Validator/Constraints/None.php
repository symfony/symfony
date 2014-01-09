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

use Symfony\Component\Validator\Constraints\AbstractComposite;
use Symfony\Component\Validator\Exception\MissingOptionsException;


/**
 * @Annotation
 *
 * @author Marc Morera Merino <hyuhu@mmoreram.com>
 * @author Marc Morales Valldepérez <marcmorales83@gmail.com>
 *
 * @api
 */
class None extends AbstractComposite
{

    /**
     * @var string
     *
     * Message for notice Violation
     */
    public $violationMessage = 'None of this collection should pass validation.';
}
