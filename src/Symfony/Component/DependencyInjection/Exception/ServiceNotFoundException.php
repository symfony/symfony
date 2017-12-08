<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Exception;

use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\DependencyInjection\ServiceSubscriberInterface;

/**
 * This exception is thrown when a non-existent service is requested.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ServiceNotFoundException extends InvalidArgumentException implements NotFoundExceptionInterface
{
    private $id;
    private $sourceId;
    private $alternatives;

    public function __construct(string $id, string $sourceId = null, \Exception $previous = null, array $alternatives = array(), string $msg = null)
    {
        if (null !== $msg) {
            // no-op
        } elseif (null === $sourceId) {
            $msg = sprintf('You have requested a non-existent service "%s".', $id);
        } else {
            $msg = sprintf('The service "%s" has a dependency on a non-existent service "%s".', $sourceId, $id);
        }

        $subscriber = null;
        foreach (array_reverse(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)) as $trace) {
            if (isset($trace['class']) && in_array(ServiceSubscriberInterface::class, class_implements($trace['class']))) {
                $subscriber = $trace['class'];
                break;
            }
        }

        if (null !== $subscriber) {
            $msg .= sprintf(' Did you forget to add it to "%s::getSubscribedServices()"?', $subscriber);
        } elseif ($alternatives) {
            if (1 == count($alternatives)) {
                $msg .= ' Did you mean this: "';
            } else {
                $msg .= ' Did you mean one of these: "';
            }
            $msg .= implode('", "', $alternatives).'"?';
        }

        parent::__construct($msg, 0, $previous);

        $this->id = $id;
        $this->sourceId = $sourceId;
        $this->alternatives = $alternatives;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getSourceId()
    {
        return $this->sourceId;
    }

    public function getAlternatives()
    {
        return $this->alternatives;
    }
}
