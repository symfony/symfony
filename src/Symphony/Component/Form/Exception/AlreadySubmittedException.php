<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Exception;

/**
 * Thrown when an operation is called that is not acceptable after submitting
 * a form.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class AlreadySubmittedException extends LogicException
{
}
