<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Exception;

use \InvalidArgumentException as BaseInvalidArgumentException;

/**
 * InvalidArgumentException
 *
 * @author Bulat Shakirzyanov <bulat@theopenskyproject.com>
 */
class InvalidArgumentException extends BaseInvalidArgumentException implements Exception
{
}
