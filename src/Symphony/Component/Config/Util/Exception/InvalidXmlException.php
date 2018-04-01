<?php
/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Config\Util\Exception;

/**
 * Exception class for when XML parsing with an XSD schema file path or a callable validator produces errors unrelated
 * to the actual XML parsing.
 *
 * @author Ole Rößner <ole@roessner.it>
 */
class InvalidXmlException extends XmlParsingException
{
}
