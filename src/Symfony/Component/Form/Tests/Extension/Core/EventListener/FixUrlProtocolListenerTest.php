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
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\EventListener\FixUrlProtocolListener;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormConfigBuilder;
use Symfony\Component\Form\FormEvent;

class FixUrlProtocolListenerTest extends TestCase
{
    /**
     * @dataProvider provideUrlToFix
     */
    public function testFixUrl($data)
    {
        $form = new Form(new FormConfigBuilder('name', null, new EventDispatcher()));
        $event = new FormEvent($form, $data);

        $filter = new FixUrlProtocolListener('http');
        $filter->onSubmit($event);

        $this->assertSame('http://'.$data, $event->getData());
    }

    public static function provideUrlToFix()
    {
        return [
            ['www.symfony.com'],
            ['symfony.com/doc'],
            ['twitter.com/@symfony'],
            ['symfony.com?foo@bar'],
            ['symfony.com#foo@bar'],
            ['localhost'],
        ];
    }

    /**
     * @dataProvider provideUrlToSkip
     */
    public function testSkipUrl($url)
    {
        $form = new Form(new FormConfigBuilder('name', null, new EventDispatcher()));
        $event = new FormEvent($form, $url);

        $filter = new FixUrlProtocolListener('http');
        $filter->onSubmit($event);

        $this->assertSame($url, $event->getData());
    }

    public static function provideUrlToSkip()
    {
        return [
            ['http://www.symfony.com'],
            ['ftp://www.symfony.com'],
            ['https://twitter.com/@symfony'],
            ['chrome-extension://foo'],
            ['h323://foo'],
            ['iris.beep://foo'],
            ['foo+bar://foo'],
            ['fabien@symfony.com'],
            ['//relative/url'],
            ['/relative/url'],
            ['./relative/url'],
        ];
    }
}
