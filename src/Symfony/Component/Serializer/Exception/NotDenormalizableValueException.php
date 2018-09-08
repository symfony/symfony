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
 * @author Claudio Beatrice <claudi0.beatric3@gmail.com>
 */
class NotDenormalizableValueException extends NotNormalizableValueException
{
    /**
     * @var null|string
     */
    private $field;

    public function __construct(string $message = '', ?string $field = null, \Throwable $previous = null)
    {
        @trigger_error(sprintf('The %s class will stop extending %s in Symfony 5.0, where it will extend %s instead.', self::class, NotNormalizableValueException::class, UnexpectedValueException::class), E_USER_NOTICE);

        parent::__construct($message, $previous ? $previous->getCode() : 0, $previous);

        $this->field = $field;
    }

    public function getField(): ?string
    {
        return $this->field;
    }
}
