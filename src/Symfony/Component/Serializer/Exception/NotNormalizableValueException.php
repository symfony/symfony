<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Exception;

/**
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 */
class NotNormalizableValueException extends UnexpectedValueException
{
    private $currentType;
    private $expectedTypes;
    private $path;
    private $useMessageForUser = false;

    /**
     * @param bool $useMessageForUser If the message passed to this exception is something that can be shown
     *                                safely to your user. In other words, avoid catching other exceptions and
     *                                passing their message directly to this class.
     */
    public static function createForUnexpectedDataType(string $message, $data, array $expectedTypes, ?string $path = null, bool $useMessageForUser = false, int $code = 0, ?\Throwable $previous = null): self
    {
        $self = new self($message, $code, $previous);

        $self->currentType = get_debug_type($data);
        $self->expectedTypes = $expectedTypes;
        $self->path = $path;
        $self->useMessageForUser = $useMessageForUser;

        return $self;
    }

    public function getCurrentType(): ?string
    {
        return $this->currentType;
    }

    /**
     * @return string[]|null
     */
    public function getExpectedTypes(): ?array
    {
        return $this->expectedTypes;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function canUseMessageForUser(): ?bool
    {
        return $this->useMessageForUser;
    }
}
