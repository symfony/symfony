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
use Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Activation strategy that ignores 404s for certain URLs.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Pierrick Vignand <pierrick.vignand@gmail.com>
 *
 * @final
 */
class NotFoundActivationStrategy extends ErrorLevelActivationStrategy implements ActivationStrategyInterface
{
    private $inner;
    private $exclude;
    private $requestStack;

    /**
     * @param ActivationStrategyInterface|int|string $inner an ActivationStrategyInterface to decorate
     */
    public function __construct(RequestStack $requestStack, array $excludedUrls, $inner)
    {
        if (!$inner instanceof ActivationStrategyInterface) {
            trigger_deprecation('symfony/monolog-bridge', '5.2', 'Passing an actionLevel (int|string) as constructor\'s 3rd argument of "%s" is deprecated, "%s" expected.', __CLASS__, ActivationStrategyInterface::class);

            $actionLevel = $inner;
            $inner = new ErrorLevelActivationStrategy($actionLevel);
        }

        $this->inner = $inner;
        $this->requestStack = $requestStack;
        $this->exclude = '{('.implode('|', $excludedUrls).')}i';
    }

    public function isHandlerActivated(array $record): bool
    {
        $isActivated = $this->inner->isHandlerActivated($record);

        if (
            $isActivated
            && isset($record['context']['exception'])
            && $record['context']['exception'] instanceof HttpException
            && 404 == $record['context']['exception']->getStatusCode()
            && ($request = $this->requestStack->getMainRequest())
        ) {
            return !preg_match($this->exclude, $request->getPathInfo());
        }

        return $isActivated;
    }
}
