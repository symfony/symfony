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
 * UnexpectedValuesException.
 *
 * @author Claudio Beatrice <claudi0.beatric3@gmail.com>
 */
class UnexpectedValuesException extends \RuntimeException implements ExceptionInterface
{
    /**
     * @var string[]
     */
    private $unexpectedValueErrors;

    public function __construct(array $unexpectedValueErrors)
    {
        parent::__construct();

        $this->unexpectedValueErrors = $unexpectedValueErrors;
    }

    /**
     * @return string[]
     */
    public function getUnexpectedValueErrors(): array
    {
        return $this->unexpectedValueErrors;
    }
}
