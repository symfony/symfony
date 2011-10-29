<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Core\Type;

require_once __DIR__ . '/LocalizedTestCase.php';


class UrlTypeTest extends LocalizedTestCase
{
    public function testSubmitAddsDefaultProtocolIfNoneIsIncluded()
    {
        $form = $this->factory->create('url', 'name');

        $form->bind('www.domain.com');

        $this->assertSame('http://www.domain.com', $form->getData());
        $this->assertSame('http://www.domain.com', $form->getClientData());
    }

    public function testSubmitAddsNoDefaultProtocolIfAlreadyIncluded()
    {
        $form = $this->factory->create('url', null, array(
            'default_protocol' => 'http',
        ));

        $form->bind('ftp://www.domain.com');

        $this->assertSame('ftp://www.domain.com', $form->getData());
        $this->assertSame('ftp://www.domain.com', $form->getClientData());
    }

    public function testSubmitAddsNoDefaultProtocolIfEmpty()
    {
        $form = $this->factory->create('url', null, array(
            'default_protocol' => 'http',
        ));

        $form->bind('');

        $this->assertSame(null, $form->getData());
        $this->assertSame('', $form->getClientData());
    }

    public function testSubmitAddsNoDefaultProtocolIfSetToNull()
    {
        $form = $this->factory->create('url', null, array(
            'default_protocol' => null,
        ));

        $form->bind('www.domain.com');

        $this->assertSame('www.domain.com', $form->getData());
        $this->assertSame('www.domain.com', $form->getClientData());
    }
}
