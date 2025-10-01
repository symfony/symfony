<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Handles the IsCsrfTokenValid attribute on controllers.
 */
final class IsCsrfTokenValidAttributeListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private ?ExpressionLanguage $expressionLanguage = null,
    ) {
    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        if (!$attributes = $event->getAttributes(IsCsrfTokenValid::class)) {
            return;
        }

        $request = $event->getRequest();
        $arguments = $event->getNamedArguments();

        foreach ($attributes as $attribute) {
            $id = $this->getTokenId($attribute->id, $request, $arguments);
            $methods = array_map('strtoupper', (array) $attribute->methods);

            if ($methods && !\in_array($request->getMethod(), $methods, true)) {
                continue;
            }

            $tokenValue = $this->getTokenValue($request, $attribute->tokenSource, $attribute->tokenKey);
            if (
                null === $tokenValue
                || !$this->csrfTokenManager->isTokenValid(new CsrfToken($id, $tokenValue))
            ) {
                throw new InvalidCsrfTokenException('Invalid CSRF token.');
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER_ARGUMENTS => ['onKernelControllerArguments', 25]];
    }

    private function getTokenId(string|Expression $id, Request $request, array $arguments): string
    {
        if (!$id instanceof Expression) {
            return $id;
        }

        $this->expressionLanguage ??= new ExpressionLanguage();

        return (string) $this->expressionLanguage->evaluate($id, [
            'request' => $request,
            'args' => $arguments,
        ]);
    }

    private function getTokenValue(Request $request, int $tokenSource, string $tokenKey): ?string
    {
        $sources = [
            IsCsrfTokenValid::SOURCE_PAYLOAD => static fn () => $request->getPayload()->get($tokenKey),
            IsCsrfTokenValid::SOURCE_QUERY => static fn () => $request->query->get($tokenKey),
            IsCsrfTokenValid::SOURCE_HEADER => static fn () => $request->headers->get($tokenKey),
        ];

        foreach ($sources as $source => $getter) {
            if (!($tokenSource & $source)) {
                continue;
            }

            if (null !== $token = $getter()) {
                return $token;
            }
        }

        return null;
    }
}
