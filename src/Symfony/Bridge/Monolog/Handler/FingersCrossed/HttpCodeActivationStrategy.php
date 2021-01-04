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
 * Activation strategy that ignores certain HTTP codes.
 *
 * @author Shaun Simmons <shaun@envysphere.com>
 */
class HttpCodeActivationStrategy extends ErrorLevelActivationStrategy
{
    private $exclusions;
    private $requestStack;

    /**
     * @param array $exclusions each exclusion must have a "code" and "urls" keys
     */
    public function __construct(RequestStack $requestStack, array $exclusions, $actionLevel)
    {
        foreach ($exclusions as $exclusion) {
            if (!\array_key_exists('code', $exclusion)) {
                throw new \LogicException('An exclusion must have a "code" key.');
            }
            if (!\array_key_exists('urls', $exclusion)) {
                throw new \LogicException('An exclusion must have a "urls" key.');
            }
        }

        parent::__construct($actionLevel);

        $this->requestStack = $requestStack;
        $this->exclusions = $exclusions;
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
            && ($request = $this->requestStack->getMasterRequest())
        ) {
            foreach ($this->exclusions as $exclusion) {
                if ($record['context']['exception']->getStatusCode() !== $exclusion['code']) {
                    continue;
                }

                if (\count($exclusion['urls'])) {
                    return !preg_match('{('.implode('|', $exclusion['urls']).')}i', $request->getPathInfo());
                }

                return false;
            }
        }

        return $isActivated;
    }
}
