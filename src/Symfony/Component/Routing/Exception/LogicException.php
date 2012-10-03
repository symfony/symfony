<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Exception;

/**
 * Exception that represents error in the program logic inside the Routing
 * Component
 *
 * @author Romain Neutron <imprec@gmail.com>
 */
class LogicException extends \LogicException implements ExceptionInterface
{
}
