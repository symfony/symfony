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
    /**
     * @param string          $key                  The requested parameter key
     * @param string|null     $sourceId             The service id that references the non-existent parameter
     * @param string|null     $sourceKey            The parameter key that references the non-existent parameter
     * @param \Throwable|null $previous             The previous exception
     * @param string[]        $alternatives         Some parameter name alternatives
     * @param string|null     $nonNestedAlternative The alternative parameter name when the user expected dot notation for nested parameters
     */
    public function __construct(
        private string $key,
        private ?string $sourceId = null,
        private ?string $sourceKey = null,
        ?\Throwable $previous = null,
        private array $alternatives = [],
        private ?string $nonNestedAlternative = null,
        private ?string $sourceExtensionName = null,
        private ?string $extraMessage = null,
    ) {
        parent::__construct('', 0, $previous);

        $this->updateRepr();
    }

    public function updateRepr(): void
    {
        if (null !== $this->sourceId) {
            $this->message = \sprintf('The service "%s" has a dependency on a non-existent parameter "%s".', $this->sourceId, $this->key);
        } elseif (null !== $this->sourceKey) {
            $this->message = \sprintf('The parameter "%s" has a dependency on a non-existent parameter "%s".', $this->sourceKey, $this->key);
        } elseif (null !== $this->sourceExtensionName) {
            $this->message = \sprintf('You have requested a non-existent parameter "%s" while loading extension "%s".', $this->key, $this->sourceExtensionName);
        } elseif ('.' === ($this->key[0] ?? '')) {
            $this->message = \sprintf('Parameter "%s" not found. It was probably deleted during the compilation of the container.', $this->key);
        } else {
            $this->message = \sprintf('You have requested a non-existent parameter "%s".', $this->key);
        }

        if ($this->alternatives) {
            if (1 === \count($this->alternatives)) {
                $this->message .= ' Did you mean this: "';
            } else {
                $this->message .= ' Did you mean one of these: "';
            }
            $this->message .= implode('", "', $this->alternatives).'"?';
        } elseif (null !== $this->nonNestedAlternative) {
            $this->message .= ' You cannot access nested array items, do you want to inject "'.$this->nonNestedAlternative.'" instead?';
        }

        if ($this->extraMessage) {
            $this->message .= ' '.$this->extraMessage;
        }
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getSourceId(): ?string
    {
        return $this->sourceId;
    }

    public function getSourceKey(): ?string
    {
        return $this->sourceKey;
    }

    public function setSourceId(?string $sourceId): void
    {
        $this->sourceId = $sourceId;

        $this->updateRepr();
    }

    public function setSourceKey(?string $sourceKey): void
    {
        $this->sourceKey = $sourceKey;

        $this->updateRepr();
    }

    public function setSourceExtensionName(?string $sourceExtensionName): void
    {
        $this->sourceExtensionName = $sourceExtensionName;

        $this->updateRepr();
    }

    public function getExtraMessage(): ?string
    {
        return $this->extraMessage;
    }

    public function setExtraMessage(?string $extraMessage): void
    {
        $this->extraMessage = $extraMessage;

        $this->updateRepr();
    }
}
