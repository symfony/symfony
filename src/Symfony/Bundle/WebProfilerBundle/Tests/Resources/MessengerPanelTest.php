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
use Symfony\Bridge\Twig\Extension\CodeExtension;
use Symfony\Bundle\WebProfilerBundle\Twig\WebProfilerExtension;
use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;
use Symfony\Component\Messenger\DataCollector\MessengerDataCollector;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\TraceableMessageBus;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class MessengerPanelTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(MessengerDataCollector::class)) {
            $this->markTestSkipped('The Messenger component is not installed.');
        }
    }

    public function testPanelRendersTheCallerExcerpt()
    {
        $bus = new TraceableMessageBus(new MessageBus());
        $bus->dispatch(new \stdClass());

        $panel = $this->renderPanel($bus);

        $this->assertStringContainsString('title="'.__FILE__.'">MessengerPanelTest.php</', $panel);
        $this->assertStringContainsString('<div class="trace"><ol start=', preg_replace('/>\s+</', '><', $panel));
    }

    public function testPanelRendersACallerWithoutFile()
    {
        $bus = new TraceableMessageBus(new MessageBus());

        // array_map() called by array_map() leaves no frame with a file, so the caller is unknown
        array_map('array_map', [$bus->dispatch(...)], [[new \stdClass()]]);

        $this->assertNull($bus->getDispatchedMessages()[0]['caller']['file']);

        $panel = $this->renderPanel($bus);

        $this->assertStringContainsString('<td class="message-bus-dispatch-caller">', $panel);
        $this->assertStringContainsString('<div class="trace"></div>', preg_replace('/>\s+</', '><', $panel));
    }

    private function renderPanel(TraceableMessageBus $bus): string
    {
        $collector = new MessengerDataCollector();
        $collector->registerBus('messenger.bus.default', $bus);
        $collector->lateCollect();
        $collector = unserialize(serialize($collector));

        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2).'/Resources/views', 'WebProfiler');

        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new WebProfilerExtension());
        $twig->addExtension(new CodeExtension(new FileLinkFormatter(), '', 'UTF-8'));

        return $twig
            ->load('@WebProfiler/Collector/messenger.html.twig')
            ->renderBlock('panel', ['collector' => $collector])
        ;
    }
}
