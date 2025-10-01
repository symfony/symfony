<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Mime;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * @author Alexander Hofbauer <a.hofbauer@fify.at
 */
class WrappedTemplatedEmailTest extends TestCase
{
    public function testEmailImage()
    {
        $email = $this->buildEmail('email/image.html.twig');
        $body = $email->toString();
        $contentId1 = $email->getAttachments()[0]->getContentId();
        $contentId2 = $email->getAttachments()[1]->getContentId();

        $part1 = str_replace("\n", "\r\n",
            <<<PART
                Content-ID: <$contentId1>
                Content-Type: image/png; name="$contentId1"
                Content-Transfer-Encoding: base64
                Content-Disposition: inline;
                 name="$contentId1";
                 filename="@assets/images/logo1.png"

                PART
        );

        $part2 = str_replace("\n", "\r\n",
            <<<PART
                Content-ID: <$contentId2>
                Content-Type: image/png; name="$contentId2"
                Content-Transfer-Encoding: base64
                Content-Disposition: inline;
                 name="$contentId2"; filename=image.png

                PART
        );

        self::assertStringContainsString('![](cid:@assets/images/logo1.png)![](cid:image.png)', $body);
        self::assertStringContainsString($part1, $body);
        self::assertStringContainsString($part2, $body);
    }

    public function testEmailAttach()
    {
        $email = $this->buildEmail('email/attach.html.twig');
        $body = $email->toString();

        $part1 = str_replace("\n", "\r\n",
            <<<PART
                Content-Type: image/png; name=logo1.png
                Content-Transfer-Encoding: base64
                Content-Disposition: attachment; name=logo1.png; filename=logo1.png

                PART
        );

        $part2 = str_replace("\n", "\r\n",
            <<<PART
                Content-Type: image/png; name=image.png
                Content-Transfer-Encoding: base64
                Content-Disposition: attachment; name=image.png; filename=image.png

                PART
        );

        self::assertStringContainsString($part1, $body);
        self::assertStringContainsString($part2, $body);
    }

    private function buildEmail(string $template): TemplatedEmail
    {
        $email = (new TemplatedEmail())
            ->from('a.hofbauer@fify.at')
            ->htmlTemplate($template);

        $loader = new FilesystemLoader(\dirname(__DIR__).'/Fixtures/templates/');
        $loader->addPath(\dirname(__DIR__).'/Fixtures/assets', 'assets');

        $environment = new Environment($loader);
        $renderer = new BodyRenderer($environment);
        $renderer->render($email);

        return $email;
    }
}
