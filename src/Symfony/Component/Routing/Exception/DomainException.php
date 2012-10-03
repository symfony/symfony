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
 * Exception thrown if a value does not adhere to a defined valid data domain
 * inside the Routing Component
 *
 * @author Romain Neutron <imprec@gmail.com>
 */
class DomainException extends \DomainException implements ExceptionInterface
{
}
