<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Exception;

/**
 * @author Eriksen Costa <eriksen.costa@infranology.com.br>
 *
 * @deprecated since Symfony 5.3, use symfony/polyfill-intl-icu ^1.21 instead
 */
class MethodNotImplementedException extends NotImplementedException
{
    /**
     * @param string $methodName The name of the method
     */
    public function __construct(string $methodName)
    {
        parent::__construct(sprintf('The %s() is not implemented.', $methodName));
    }
}
