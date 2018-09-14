<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormError;
use Symfony\Component\Validator\ConstraintViolation;

class FormErrorTest extends TestCase
{
    public function testSerializeFormError()
    {
        $simpleXMLElement = new \SimpleXMLElement('<foo></foo>');
        $cause = new ConstraintViolation('Error 1!', null, array(), $simpleXMLElement, '', null, null, '');
        $formError = new FormError('Error 1!', null, array(), null, $cause);
        $unserializedError = unserialize(serialize($formError));

        $this->assertInstanceOf('Symfony\Component\Form\FormError', $unserializedError);
        $this->assertSame('Error 1!', $unserializedError->getMessage());
        $this->assertSame('Error 1!', $unserializedError->getCause()->getMessage());
        $this->assertNull($unserializedError->getCause()->getCause());
    }
}
