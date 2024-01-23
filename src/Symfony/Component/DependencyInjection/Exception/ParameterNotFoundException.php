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

/**
 * This exception is thrown when a non-existent parameter is used.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ParameterNotFoundException extends InvalidArgumentException implements NotFoundExceptionInterface
{
    private $key;
    private $sourceId;
    private $sourceKey;
    private $alternatives;
    private $nonNestedAlternative;

    /**
     * @param string          $key                  The requested parameter key
     * @param string|null     $sourceId             The service id that references the non-existent parameter
     * @param string|null     $sourceKey            The parameter key that references the non-existent parameter
     * @param \Throwable|null $previous             The previous exception
     * @param string[]        $alternatives         Some parameter name alternatives
     * @param string|null     $nonNestedAlternative The alternative parameter name when the user expected dot notation for nested parameters
     */
    public function __construct(string $key, ?string $sourceId = null, ?string $sourceKey = null, ?\Throwable $previous = null, array $alternatives = [], ?string $nonNestedAlternative = null)
    {
        $this->key = $key;
        $this->sourceId = $sourceId;
        $this->sourceKey = $sourceKey;
        $this->alternatives = $alternatives;
        $this->nonNestedAlternative = $nonNestedAlternative;

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
            if (1 == \count($this->alternatives)) {
                $this->message .= ' Did you mean this: "';
            } else {
                $this->message .= ' Did you mean one of these: "';
            }
            $this->message .= implode('", "', $this->alternatives).'"?';
        } elseif (null !== $this->nonNestedAlternative) {
            $this->message .= ' You cannot access nested array items, do you want to inject "'.$this->nonNestedAlternative.'" instead?';
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

    public function setSourceId(?string $sourceId)
    {
        $this->sourceId = $sourceId;

        $this->updateRepr();
    }

    public function setSourceKey(?string $sourceKey)
    {
        $this->sourceKey = $sourceKey;

        $this->updateRepr();
    }
}
