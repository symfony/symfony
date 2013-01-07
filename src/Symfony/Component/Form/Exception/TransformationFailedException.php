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
 * Indicates a value transformation error.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TransformationFailedException extends \RuntimeException implements ExceptionInterface
{
}
