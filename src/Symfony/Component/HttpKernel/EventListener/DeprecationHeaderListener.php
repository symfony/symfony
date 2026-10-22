<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds the deprecation headers configured via the "_deprecation" route default.
 *
 * Adds the RFC 9745 "Deprecation" header, the RFC 8594 "Sunset" header and
 * a "Link" header pointing to the deprecation documentation to the response.
 * Headers already set by the controller are left untouched.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9745.html
 * @see https://www.rfc-editor.org/rfc/rfc8594.html
 *
 * @author Mohamed Senoussi <lesfootix@gmail.com>
 */
class DeprecationHeaderListener implements EventSubscriberInterface
{
    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $deprecation = $request->attributes->get('_deprecation');

        if (!\is_array($deprecation)) {
            return;
        }

        $headers = $event->getResponse()->headers;

        if ($headers->has('Deprecation')) {
            return;
        }

        $route = $request->attributes->get('_route', '');
        $since = self::toDate('since', $deprecation['since'] ?? null, $route);
        $sunset = isset($deprecation['sunset']) ? self::toDate('sunset', $deprecation['sunset'], $route) : null;

        if ($sunset && $sunset < $since) {
            throw new \LogicException(\sprintf('The "sunset" date of the deprecation of route "%s" cannot be earlier than its "since" date.', $route));
        }

        $headers->set('Deprecation', '@'.$since->getTimestamp());

        if (null !== $sunset && !$headers->has('Sunset')) {
            $headers->set('Sunset', $sunset->setTimezone(new \DateTimeZone('UTC'))->format('D, d M Y H:i:s').' GMT');
        }

        if (isset($deprecation['link'])) {
            $headers->set('Link', \sprintf('<%s>; rel="deprecation"', $deprecation['link']), false);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    private static function toDate(string $key, mixed $date, string $route): \DateTimeImmutable
    {
        if (!\is_string($date)) {
            throw new \LogicException(\sprintf('The "%s" date of the deprecation of route "%s" must be a string, "%s" given.', $key, $route, get_debug_type($date)));
        }

        try {
            return new \DateTimeImmutable($date, new \DateTimeZone('UTC'));
        } catch (\Exception $e) {
            throw new \LogicException(\sprintf('The "%s" date of the deprecation of route "%s" must be a valid date, "%s" given.', $key, $route, $date), 0, $e);
        }
    }
}
