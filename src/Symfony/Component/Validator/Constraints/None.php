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
 * @author Marc Morera Merino <yuhu@mmoreram.com>
 * @author Marc Morales Valldepérez <marcmorales83@gmail.com>
 */
class None extends AbstractComposite
{

    /**
     * @var string
     *
     * Message for notice Violation
     */
    public $message = 'None of this collection should pass validation.';
}
