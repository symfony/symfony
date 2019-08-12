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

use Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Activation strategy that ignores 404s for certain URLs.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class NotFoundActivationStrategy extends ErrorLevelActivationStrategy
{
    private $blacklist;
    private $requestStack;

    public function __construct(RequestStack $requestStack, array $excludedUrls, $actionLevel)
    {
        parent::__construct($actionLevel);

        $this->requestStack = $requestStack;
        $this->blacklist = '{('.implode('|', $excludedUrls).')}i';
    }

    /**
     * @return bool
     */
    public function isHandlerActivated(array $record)
    {
        $isActivated = parent::isHandlerActivated($record);

        if (
            $isActivated
            && isset($record['context']['exception'])
            && $record['context']['exception'] instanceof HttpException
            && 404 == $record['context']['exception']->getStatusCode()
            && ($request = $this->requestStack->getMasterRequest())
        ) {
            return !preg_match($this->blacklist, $request->getPathInfo());
        }

        return $isActivated;
    }
}
