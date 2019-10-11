<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler\Exception;

use Symfony\Component\ErrorHandler\ThrowableUtils;

class ErrorException extends \ErrorException
{
    private $originalClassName;

    public function __construct(\Throwable $e)
    {
        $this->originalClassName = \get_class($e);

        parent::__construct(
            $e->getMessage(),
            $e->getCode(),
            ThrowableUtils::getSeverity($e),
            $e->getFile(),
            $e->getLine(),
            $e->getPrevious()
        );

        $refl = new \ReflectionProperty(\Exception::class, 'trace');
        $refl->setAccessible(true);
        $refl->setValue($this, $e->getTrace());
    }

    public function getOriginalClassName(): string
    {
        return $this->originalClassName;
    }
}
