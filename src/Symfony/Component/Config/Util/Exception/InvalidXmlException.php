<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Util\Exception;

/**
 * Exception class for when XML parsing with an XSD schema file path or a callable validator produces errors unrelated
 * to the actual XML parsing.
 */
class InvalidXmlException extends XmlParsingException
{
}
