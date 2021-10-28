<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\EventListener\FixUrlProtocolListener;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormConfigBuilder;
use Symfony\Component\Form\FormEvent;

class FixUrlProtocolListenerTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testFixHttpUrl()
    {
        $data = 'www.symfony.com';
        $form = new Form(new FormConfigBuilder('name', null, new EventDispatcher()));
        $event = new FormEvent($form, $data);

        $filter = new FixUrlProtocolListener('http');
        $filter->onSubmit($event);

        $this->assertEquals('http://www.symfony.com', $event->getData());
    }

    public function provideUrlsWithSupportedProtocols()
    {
        return [
            ['http://www.symfony.com'],
            ['ftp://www.symfony.com'],
            ['chrome-extension://foo'],
            ['h323://foo'],
            ['iris.beep://foo'],
            ['foo+bar://foo'],
            ['fabien@symfony.com'],
            ['foo'],
        ];
    }

    /**
     * @dataProvider provideUrlsWithSupportedProtocols
     */
    public function testSkipOtherProtocol($url)
    {
        $form = new Form(new FormConfigBuilder('name', null, new EventDispatcher()));
        $event = new FormEvent($form, $url);

        $filter = new FixUrlProtocolListener('http');
        $filter->onSubmit($event);

        $this->assertEquals($url, $event->getData());
    }
}
