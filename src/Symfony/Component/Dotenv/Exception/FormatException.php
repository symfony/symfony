<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Dotenv\Exception;

/**
 * Thrown when a file has a syntax error.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class FormatException extends \LogicException implements ExceptionInterface
{
    private $context;

    public function __construct(string $message, FormatExceptionContext $context, int $code = 0, \Exception $previous = null)
    {
        $this->context = $context;

        parent::__construct(sprintf("%s in \"%s\" at line %d.\n%s", $message, $context->getPath(), $context->getLineno(), $context->getDetails()), $code, $previous);
    }

    public function getContext(): FormatExceptionContext
    {
        return $this->context;
    }
}
