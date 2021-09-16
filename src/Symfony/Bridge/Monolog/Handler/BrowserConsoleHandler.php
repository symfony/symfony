<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Handler;

use Monolog\Handler\BrowserConsoleHandler as BaseBrowserConsoleHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class BrowserConsoleHandler extends BaseBrowserConsoleHandler implements EventSubscriberInterface
{
    /** @var string */
    private static $responseFormat;

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    private static function determineResponseFormatFromContentType(?string $contentType): string
    {
        if (null === $contentType) {
            return 'unknown';
        }
        if (stripos($contentType, 'application/javascript') !== false || stripos($contentType, 'text/javascript') !== false) {
            return 'js';
        }
        if (stripos($contentType, 'text/html') === false) {
            return 'unknown';
        }

        return 'html';
    }

    public static function onResponse(ResponseEvent $responseEvent): void
    {
        $contentType = $responseEvent->getResponse()->headers->get('Content-Type');
        static::$responseFormat = static::determineResponseFormatFromContentType($contentType);
        static::send();
    }

    protected static function getResponseFormat(): string
    {
        return static::$responseFormat;
    }

    protected function registerShutdownFunction(): void
    {
    }
}
