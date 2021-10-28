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

    /**
     * @group legacy
     * @dataProvider provideNonUrls
     */
    public function testDeprecatedFixEmail($url)
    {
        $this->expectDeprecation('Since symfony/form 5.4: Form type "url", does not add a default protocol to urls that looks like emails or does not contain a dot or slash.');

        $form = new Form(new FormConfigBuilder('name', null, new EventDispatcher()));
        $event = new FormEvent($form, $url);

        $filter = new FixUrlProtocolListener('http');
        $filter->onSubmit($event);

        $this->assertEquals($url, $event->getData());
    }

    public function provideNonUrls()
    {
        return [
            ['fabien@symfony.com'],
            ['foo'],
        ];
    }
}
