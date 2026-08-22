<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\Resources;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\WebProfilerBundle\Twig\WebProfilerExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\DataCollector\MessageDataCollector;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\EventListener\MessageLoggerListener;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
use Symfony\Component\Mime\RawMessage;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class MailerPanelTest extends TestCase
{
    private string $attachmentPath;

    protected function setUp(): void
    {
        if (!class_exists(MessageDataCollector::class)) {
            $this->markTestSkipped('The Mailer component is not installed.');
        }

        $this->attachmentPath = tempnam(sys_get_temp_dir(), 'sf_mailer_panel_');
        file_put_contents($this->attachmentPath, 'attachment content');
    }

    protected function tearDown(): void
    {
        if (isset($this->attachmentPath)) {
            @unlink($this->attachmentPath);
        }
    }

    public function testPanelRendersTheAttachmentAndTheRawMessage()
    {
        $panel = $this->renderPanel($this->createEmail(), false);

        $this->assertStringContainsString('Attachments <span>(1 file / 18 bytes)</span>', $panel);
        $this->assertStringContainsString('file.txt', $panel);
        $this->assertStringContainsString('Download as EML file', $panel);
        $this->assertStringNotContainsString('not readable anymore', $panel);
    }

    public function testPanelRendersWhenTheAttachedFileIsDeleted()
    {
        $panel = $this->renderPanel($this->createEmail(), true);

        $this->assertStringContainsString('Attachments <span>(1 file / 0 bytes)</span>', $panel);
        $this->assertStringContainsString('(the file is not readable anymore)', $panel);
        $this->assertStringContainsString('The raw message is not readable anymore.', $panel);
        $this->assertStringNotContainsString('Download as EML file', $panel);
    }

    public function testPanelRendersWhenTheFileOfANonEmailMessageIsDeleted()
    {
        $headers = (new Headers())->addMailboxListHeader('From', ['fabien@example.com']);
        $message = new Message($headers, new DataPart(new File($this->attachmentPath), 'file.txt', 'text/plain'));

        $panel = $this->renderPanel($message, true);

        $this->assertStringContainsString('The body is not readable anymore.', $panel);
        $this->assertStringContainsString('The raw message is not readable anymore.', $panel);
    }

    private function createEmail(): Email
    {
        $email = (new Email())
            ->from('fabien@example.com')
            ->to('helene@example.com')
            ->subject('Hello')
            ->text('Hello!')
            ->html('<p>Hello!</p>')
        ;
        $email->attachFromPath($this->attachmentPath, 'file.txt', 'text/plain');

        return $email;
    }

    private function renderPanel(RawMessage $message, bool $deleteFile): string
    {
        $listener = new MessageLoggerListener();
        $listener->onMessage(new MessageEvent($message, new Envelope(new Address('fabien@example.com'), [new Address('helene@example.com')]), 'null://'));

        $collector = new MessageDataCollector($listener);
        $collector->collect(new Request(), new Response());

        // the profiler renders the panel from the stored profile, long after the request
        $collector = unserialize(serialize($collector));

        if ($deleteFile) {
            unlink($this->attachmentPath);
        }

        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2).'/Resources/views', 'WebProfiler');

        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new WebProfilerExtension());

        return $twig
            ->load('@WebProfiler/Collector/mailer.html.twig')
            ->renderBlock('panel', ['collector' => $collector])
        ;
    }
}
