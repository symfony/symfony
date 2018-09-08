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
class UnexpectedValuesException extends \RuntimeException implements ExceptionInterface
{
    /**
     * @var UnexpectedValueException[]
     */
    private $unexpectedValueErrors;

    /**
     * @param UnexpectedValueException[] $unexpectedValueErrors
     */
    public function __construct(array $unexpectedValueErrors)
    {
        $this->validateErrors($unexpectedValueErrors);

        parent::__construct();

        $this->unexpectedValueErrors = $unexpectedValueErrors;
    }

    /**
     * @return UnexpectedValueException[]
     */
    public function getUnexpectedValueErrors(): array
    {
        return $this->unexpectedValueErrors;
    }

    /**
     * @param mixed[] $unexpectedValueErrors
     *
     * @throws InvalidArgumentException
     */
    private function validateErrors(array $unexpectedValueErrors): void
    {
        foreach ($unexpectedValueErrors as $unexpectedValueError) {
            if (!$unexpectedValueError instanceof UnexpectedValueException) {
                throw new InvalidArgumentException(
                    sprintf(
                        'All errors must be instances of %s, %s given.',
                        UnexpectedValuesException::class,
                        $this->getType($unexpectedValueError)
                    )
                );
            }
        }
    }

    /**
     * @param mixed $unexpectedValueError
     */
    private function getType($unexpectedValueError): string
    {
        return is_object($unexpectedValueError)
            ? get_class($unexpectedValueError)
            : gettype($unexpectedValueError);
    }
}
