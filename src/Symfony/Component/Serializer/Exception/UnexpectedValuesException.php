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
     * @param array<string, UnexpectedValueException[]> $unexpectedValueErrors
     */
    public function __construct(array $unexpectedValueErrors)
    {
        $this->validateErrors($unexpectedValueErrors);

        parent::__construct();

        $this->unexpectedValueErrors = $unexpectedValueErrors;
    }

    /**
     * @return array<string, UnexpectedValueException[]>
     */
    public function getUnexpectedValueErrors(): array
    {
        return $this->unexpectedValueErrors;
    }

    /**
     * @param array<string, UnexpectedValueException[]> $unexpectedValueErrors
     *
     * @throws InvalidArgumentException
     */
    private function validateErrors(array $unexpectedValueErrors): void
    {
        $this->assertNotEmpty($unexpectedValueErrors, 'No errors were given, at least one is expected.');

        foreach ($unexpectedValueErrors as $field => $fieldUnexpectedValueErrors) {
            $this->assertIsString($field, sprintf('All keys must be strings, %s given.', $this->getType($field)));
            $this->assertNotEmpty($fieldUnexpectedValueErrors, sprintf('No errors were given for key "%s", at least one is expected.', $field));

            foreach ((array) $fieldUnexpectedValueErrors as $fieldUnexpectedValueError) {
                $this->assertIsError($fieldUnexpectedValueError, sprintf(
                    'All errors must be instances of %s, %s given for key "%s".',
                    UnexpectedValueException::class,
                    $this->getType($fieldUnexpectedValueError),
                    $field
                ), $unexpectedValueErrors);
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

    /**
     * @param array $values
     */
    private function assertNotEmpty(array $values, string $message): void
    {
        if (empty($values)) {
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * @param mixed $value
     */
    private function assertIsString($value, string $message): void
    {
        if ($this->getType($value) !== 'string') {
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * @param mixed $value
     */
    private function assertIsError($value, string $message, $unexpectedValueErrors): void
    {
        if (!$value instanceof UnexpectedValueException) {
            throw new InvalidArgumentException($message);
        }
    }
}
