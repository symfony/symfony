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
 * Base exception class.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated This class is a replacement for when class FormException was
 *             used previously. It should not be used and will be removed.
 *             Occurrences of this class should be replaced by more specialized
 *             exception classes, preferably derived from SPL exceptions.
 */
class Exception extends \Exception implements ExceptionInterface
{
}
