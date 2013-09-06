<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Exception;

/**
 * Thrown when an operation is called that is not acceptable after submitting
 * a form.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class AlreadySubmittedException extends AlreadyBoundException
{
}
