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

use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class EmailController
{
    public function indexAction(MailerInterface $mailer)
    {
        $mailer->send((new Email())->to('fabien@symfony.com')->from('fabien@symfony.com')->subject('Foo')
            ->addReplyTo('me@symfony.com')
            ->addCc('cc@symfony.com')
            ->text('Bar!')
            ->html('<p>Foo</p>')
            ->addPart(new DataPart(file_get_contents(__FILE__), 'foobar.php'))
        );

        $mailer->send((new Email())->to('fabien@symfony.com', 'thomas@symfony.com')->from('fabien@symfony.com')->subject('Foo')
            ->addReplyTo(new Address('me@symfony.com', 'Fabien Potencier'))
            ->addCc('cc@symfony.com')
            ->text('Bar!')
            ->html('<p>Foo</p>')
            ->addPart(new DataPart(file_get_contents(__FILE__), 'foobar.php'))
        );

        return new Response();
    }

    public function sendEmailWithTemplateAction(MailerInterface $mailer): Response
    {
        $mailer->send($this->buildTemplatedEmail());

        return new Response();
    }

    private function buildTemplatedEmail(): TemplatedEmail
    {
        $email = (new TemplatedEmail())
            ->from('sanmartindev@gmail.com')
            ->to(new Address('other_account@example.com'))
            ->subject('Welcome')
            ->textTemplate('emails/welcome.txt.twig')
            ->htmlTemplate('emails/welcome.html.twig')
            ->context([
                'username' => 'santysisi',
                'password' => 'Mock8pass9@',
                'link' => 'https://mysuperwebapplication/activate/abcdef12-0123-abcd-5678-0123456789ab/',
            ])
        ;

        $loader = new FilesystemLoader(\dirname(__DIR__).'/templates/');
        $environment = new Environment($loader);
        $renderer = new BodyRenderer($environment);
        $renderer->render($email);

        return $email;
    }
}
