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

/**
 * This exception is thrown when a non-existent parameter is used.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ParameterNotFoundException extends InvalidArgumentException
{
    private $key;
    private $sourceId;
    private $sourceKey;
    private $alternatives;

    /**
     * @param string     $key          The requested parameter key
     * @param string     $sourceId     The service id that references the non-existent parameter
     * @param string     $sourceKey    The parameter key that references the non-existent parameter
     * @param \Exception $previous     The previous exception
     * @param string[]   $alternatives Some parameter name alternatives
     */
    public function __construct($key, $sourceId = null, $sourceKey = null, \Exception $previous = null, array $alternatives = array())
    {
        $this->key = $key;
        $this->sourceId = $sourceId;
        $this->sourceKey = $sourceKey;
        $this->alternatives = $alternatives;

        parent::__construct('', 0, $previous);

        $this->updateRepr();
    }

    public function updateRepr()
    {
        if (null !== $this->sourceId) {
            $this->message = sprintf('The service "%s" has a dependency on a non-existent parameter "%s".', $this->sourceId, $this->key);
        } elseif (null !== $this->sourceKey) {
            $this->message = sprintf('The parameter "%s" has a dependency on a non-existent parameter "%s".', $this->sourceKey, $this->key);
        } else {
            $this->message = sprintf('You have requested a non-existent parameter "%s".', $this->key);
        }

        if ($this->alternatives) {
            if (1 == count($this->alternatives)) {
                $this->message .= ' Did you mean this: "';
            } else {
                $this->message .= ' Did you mean one of these: "';
            }
            $this->message .= implode('", "', $this->alternatives).'"?';
        }
    }

    public function getKey()
    {
        return $this->key;
    }

    public function getSourceId()
    {
        return $this->sourceId;
    }

    public function getSourceKey()
    {
        return $this->sourceKey;
    }

    public function setSourceId($sourceId)
    {
        $this->sourceId = $sourceId;

        $this->updateRepr();
    }

    public function setSourceKey($sourceKey)
    {
        $this->sourceKey = $sourceKey;

        $this->updateRepr();
    }
}
