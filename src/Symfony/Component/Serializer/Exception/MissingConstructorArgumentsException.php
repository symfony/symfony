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
 * @deprecated since Symfony 6.3, use {@see MissingConstructorArgumentException} instead
 *
 * @author Maxime VEBER <maxime.veber@nekland.fr>
 */
class MissingConstructorArgumentsException extends RuntimeException
{
    /**
     * @var string[]
     */
    private $missingArguments;

    public function __construct(string $message, int $code = 0, \Throwable $previous = null, array $missingArguments = [])
    {
        if (!$this instanceof MissingConstructorArgumentException) {
            trigger_deprecation('symfony/serializer', '6.3', 'The "%s" class is deprecated, use "%s" instead.', __CLASS__, MissingConstructorArgumentException::class);
        }

        $this->missingArguments = $missingArguments;

        parent::__construct($message, $code, $previous);
    }

    /**
     * @deprecated since Symfony 6.3, use {@see MissingConstructorArgumentException::getMissingArgument()} instead
     *
     * @return string[]
     */
    public function getMissingConstructorArguments(): array
    {
        trigger_deprecation('symfony/serializer', '6.3', 'The "%s()" method is deprecated, use "%s::getMissingArgument()" instead.', __METHOD__, MissingConstructorArgumentException::class);

        return $this->missingArguments;
    }
}
