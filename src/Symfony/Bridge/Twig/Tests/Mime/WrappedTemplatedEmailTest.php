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
use Symfony\Bridge\Twig\Mime\WrappedTemplatedEmail;
use Symfony\Component\Mime\Part\DataPart;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\FilesystemLoader;

/**
 * @author Alexander Hofbauer <a.hofbauer@fify.at
 */
class WrappedTemplatedEmailTest extends TestCase
{
    public function testEmailImage()
    {
        $email = $this->buildEmail('email/image.html.twig');
        $html = $email->getHtmlBody();
        $body = $email->toString();

        $inlineParts = array_values(array_filter($email->getAttachments(), static fn (DataPart $part) => $part->hasContentId()));
        self::assertCount(3, $inlineParts);

        $contentId1 = $inlineParts[0]->getContentId();
        $contentId2 = $inlineParts[1]->getContentId();
        $contentId3 = $inlineParts[2]->getContentId();

        self::assertStringContainsString("cid:$contentId1", $html);
        self::assertStringContainsString("cid:$contentId2", $html);
        self::assertStringContainsString("cid:$contentId3", $html);

        $part1 = str_replace("\n", "\r\n",
            <<<PART
                Content-ID: <$contentId1>
                Content-Type: image/png; name=logo1.png
                Content-Transfer-Encoding: base64
                Content-Disposition: inline; name=logo1.png; filename=logo1.png

                PART
        );

        $part2 = str_replace("\n", "\r\n",
            <<<PART
                Content-ID: <$contentId2>
                Content-Type: image/png; name=image.png
                Content-Transfer-Encoding: base64
                Content-Disposition: inline; name=image.png; filename=image.png

                PART
        );

        $part3 = str_replace("\n", "\r\n",
            <<<PART
                Content-ID: <$contentId3>
                Content-Type: image/png; name="dir/custom.png"
                Content-Transfer-Encoding: base64
                Content-Disposition: inline; name="dir/custom.png";
                 filename="dir/custom.png"

                PART
        );

        self::assertStringContainsString($part1, $body);
        self::assertStringContainsString($part2, $body);
        self::assertStringContainsString($part3, $body);
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

    public function testGetReturnPathWhenNull()
    {
        $message = new TemplatedEmail();
        $email = new WrappedTemplatedEmail(new Environment(new ArrayLoader()), $message);

        $this->assertSame('', $email->getReturnPath());
    }

    public function testGetReturnPathWhenSet()
    {
        $message = (new TemplatedEmail())->returnPath('test@example.com');
        $email = new WrappedTemplatedEmail(new Environment(new ArrayLoader()), $message);

        $this->assertSame('test@example.com', $email->getReturnPath());
    }
}
