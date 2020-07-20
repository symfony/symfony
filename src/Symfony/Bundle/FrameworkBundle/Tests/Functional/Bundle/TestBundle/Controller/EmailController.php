<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmailController
{
    public function indexAction(MailerInterface $mailer)
    {
        $mailer->send((new Email())->to('fabien@symfony.com')->from('fabien@symfony.com')->subject('Foo')
            ->addReplyTo('me@symfony.com')
            ->addCc('cc@symfony.com')
            ->text('Bar!')
            ->html('<p>Foo</p>')
            ->attach(file_get_contents(__FILE__), 'foobar.php')
        );

        $mailer->send((new Email())->to('fabien@symfony.com', 'thomas@symfony.com')->from('fabien@symfony.com')->subject('Foo')
            ->addReplyTo(new Address('me@symfony.com', 'Fabien Potencier'))
            ->addCc('cc@symfony.com')
            ->text('Bar!')
            ->html('<p>Foo</p>')
            ->attach(file_get_contents(__FILE__), 'foobar.php')
        );

        return new Response();
    }
}
