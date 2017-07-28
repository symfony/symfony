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
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Activation strategy that ignores certain HTTP codes.
 *
 * @author Shaun Simmons <shaun@envysphere.com>
 */
class HttpCodeActivationStrategy extends ErrorLevelActivationStrategy
{
    private $exclusions;
    private $requestStack;

    public function __construct(RequestStack $requestStack, array $exclusions, $actionLevel)
    {
        parent::__construct($actionLevel);

        $this->requestStack = $requestStack;
        $this->exclusions = $exclusions;
    }

    public function isHandlerActivated(array $record)
    {
        $isActivated = parent::isHandlerActivated($record);

        if (
            $isActivated
            && isset($record['context']['exception'])
            && $record['context']['exception'] instanceof HttpException
            && ($request = $this->requestStack->getMasterRequest())
        ) {
            foreach ($this->exclusions as $exclusion) {
                if ($record['context']['exception']->getStatusCode() !== $exclusion['code']) {
                    continue;
                }

                $urlBlacklist = null;
                if (count($exclusion['url'])) {
                    return !preg_match('{('.implode('|', $exclusion['url']).')}i', $request->getPathInfo());
                }

                return false;
            }
        }

        return $isActivated;
    }
}
