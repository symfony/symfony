<?php

namespace Symfony\Tests\Component\Form;

require_once __DIR__ . '/LocalizedTestCase.php';

use Symfony\Component\Form\UrlField;

class UrlFieldTest extends LocalizedTestCase
{
    public function testBindAddsDefaultProtocolIfNoneIsIncluded()
    {
        $field = new UrlField('name');

        $field->bind('www.domain.com');

        $this->assertSame('http://www.domain.com', $field->getData());
        $this->assertSame('http://www.domain.com', $field->getDisplayedData());
    }

    public function testBindAddsNoDefaultProtocolIfAlreadyIncluded()
    {
        $field = new UrlField('name', array(
            'default_protocol' => 'http',
        ));

        $field->bind('ftp://www.domain.com');

        $this->assertSame('ftp://www.domain.com', $field->getData());
        $this->assertSame('ftp://www.domain.com', $field->getDisplayedData());
    }

    public function testBindAddsNoDefaultProtocolIfEmpty()
    {
        $field = new UrlField('name', array(
            'default_protocol' => 'http',
        ));

        $field->bind('');

        $this->assertSame(null, $field->getData());
        $this->assertSame('', $field->getDisplayedData());
    }

    public function testBindAddsNoDefaultProtocolIfSetToNull()
    {
        $field = new UrlField('name', array(
            'default_protocol' => null,
        ));

        $field->bind('www.domain.com');

        $this->assertSame('www.domain.com', $field->getData());
        $this->assertSame('www.domain.com', $field->getDisplayedData());
    }
}