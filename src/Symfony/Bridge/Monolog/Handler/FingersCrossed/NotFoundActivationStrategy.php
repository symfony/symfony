<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Handler\FingersCrossed;

use Monolog\Handler\FingersCrossed\ActivationStrategyInterface;
use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Activation strategy that ignores 404s for certain URLs.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Pierrick Vignand <pierrick.vignand@gmail.com>
 *
 * @deprecated since Symfony 7.4, use {@see HttpCodeActivationStrategy} instead
 */
final class NotFoundActivationStrategy implements ActivationStrategyInterface
{
    private string $exclude;

    public function __construct(
        private RequestStack $requestStack,
        array $excludedUrls,
        private ActivationStrategyInterface $inner,
    ) {
        trigger_deprecation('symfony/monolog-bridge', '7.4', 'The "%s" class is deprecated, use "%s" instead.', __CLASS__, HttpCodeActivationStrategy::class);

        $this->exclude = '{('.implode('|', $excludedUrls).')}i';
    }

    public function isHandlerActivated(LogRecord $record): bool
    {
        $isActivated = $this->inner->isHandlerActivated($record);

        if (
            $isActivated
            && isset($record->context['exception'])
            && $record->context['exception'] instanceof HttpException
            && 404 == $record->context['exception']->getStatusCode()
            && ($request = $this->requestStack->getMainRequest())
        ) {
            return !preg_match($this->exclude, $request->getPathInfo());
        }

        return $isActivated;
    }
}
