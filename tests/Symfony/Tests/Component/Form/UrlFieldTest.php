<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

require_once __DIR__ . '/LocalizedTestCase.php';

use Symfony\Component\Form\UrlField;

class UrlFieldTest extends LocalizedTestCase
{
    public function testSubmitAddsDefaultProtocolIfNoneIsIncluded()
    {
        $field = $this->factory->getUrlField('name');

        $field->submit('www.domain.com');

        $this->assertSame('http://www.domain.com', $field->getData());
        $this->assertSame('http://www.domain.com', $field->getDisplayedData());
    }

    public function testSubmitAddsNoDefaultProtocolIfAlreadyIncluded()
    {
        $field = $this->factory->getUrlField('name', array(
            'default_protocol' => 'http',
        ));

        $field->submit('ftp://www.domain.com');

        $this->assertSame('ftp://www.domain.com', $field->getData());
        $this->assertSame('ftp://www.domain.com', $field->getDisplayedData());
    }

    public function testSubmitAddsNoDefaultProtocolIfEmpty()
    {
        $field = $this->factory->getUrlField('name', array(
            'default_protocol' => 'http',
        ));

        $field->submit('');

        $this->assertSame(null, $field->getData());
        $this->assertSame('', $field->getDisplayedData());
    }

    public function testSubmitAddsNoDefaultProtocolIfSetToNull()
    {
        $field = $this->factory->getUrlField('name', array(
            'default_protocol' => null,
        ));

        $field->submit('www.domain.com');

        $this->assertSame('www.domain.com', $field->getData());
        $this->assertSame('www.domain.com', $field->getDisplayedData());
    }
}