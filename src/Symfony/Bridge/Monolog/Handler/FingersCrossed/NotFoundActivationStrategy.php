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
use Symfony\Component\HttpFoundation\Request;

/**
 * Activation strategy that ignores 404s for certain URLs.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class NotFoundActivationStrategy extends ErrorLevelActivationStrategy
{
    private $blacklist;
    private $request;

    public function __construct(array $excludedUrls, $actionLevel)
    {
        parent::__construct($actionLevel);
        $this->blacklist = '{('.implode('|', $excludedUrls).')}i';
    }

    public function isHandlerActivated(array $record)
    {
        $isActivated = parent::isHandlerActivated($record);
        if (
            $isActivated
            && $this->request
            && isset($record['context']['exception'])
            && $record['context']['exception'] instanceof HttpException
            && $record['context']['exception']->getStatusCode() == 404
        ) {
            return !preg_match($this->blacklist, $this->request->getPathInfo());
        }

        return $isActivated;
    }

    public function setRequest(Request $req = null)
    {
        $this->request = $req;
    }
}
