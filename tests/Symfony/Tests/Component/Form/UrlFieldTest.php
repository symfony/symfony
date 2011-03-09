<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
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
        $field = new UrlField('name');

        $field->submit('www.domain.com');

        $this->assertSame('http://www.domain.com', $field->getData());
        $this->assertSame('http://www.domain.com', $field->getDisplayedData());
    }

    public function testSubmitAddsNoDefaultProtocolIfAlreadyIncluded()
    {
        $field = new UrlField('name', array(
            'default_protocol' => 'http',
        ));

        $field->submit('ftp://www.domain.com');

        $this->assertSame('ftp://www.domain.com', $field->getData());
        $this->assertSame('ftp://www.domain.com', $field->getDisplayedData());
    }

    public function testSubmitAddsNoDefaultProtocolIfEmpty()
    {
        $field = new UrlField('name', array(
            'default_protocol' => 'http',
        ));

        $field->submit('');

        $this->assertSame(null, $field->getData());
        $this->assertSame('', $field->getDisplayedData());
    }

    public function testSubmitAddsNoDefaultProtocolIfSetToNull()
    {
        $field = new UrlField('name', array(
            'default_protocol' => null,
        ));

        $field->submit('www.domain.com');

        $this->assertSame('www.domain.com', $field->getData());
        $this->assertSame('www.domain.com', $field->getDisplayedData());
    }
}