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
    private ?string $currentType = null;
    private ?array $expectedTypes = null;
    private ?string $path = null;
    private bool $useMessageForUser = false;

    /**
     * @param list<string|\Stringable>|null $expectedTypes
     */
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, ?string $currentType = null, ?array $expectedTypes = null, ?string $path = null, bool $useMessageForUser = false)
    {
        parent::__construct($message, $code, $previous);

        $this->currentType = $currentType;
        $this->expectedTypes = $expectedTypes ? array_map(strval(...), $expectedTypes) : $expectedTypes;
        $this->path = $path;
        $this->useMessageForUser = $useMessageForUser;
    }

    /**
     * @param list<string|\Stringable> $expectedTypes
     * @param bool                     $useMessageForUser If the message passed to this exception is something that can be shown
     *                                                    safely to your user. In other words, avoid catching other exceptions and
     *                                                    passing their message directly to this class.
     */
    public static function createForUnexpectedDataType(string $message, mixed $data, array $expectedTypes, ?string $path = null, bool $useMessageForUser = false, int $code = 0, ?\Throwable $previous = null): self
    {
        return new self($message, $code, $previous, get_debug_type($data), $expectedTypes, $path, $useMessageForUser);
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
