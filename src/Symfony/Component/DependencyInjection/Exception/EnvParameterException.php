<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Exception;

/**
 * This exception wraps exceptions whose messages contain a reference to an env parameter.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class EnvParameterException extends InvalidArgumentException
{
    public function __construct(array $usedEnvs, \Exception $previous = null)
    {
        parent::__construct(sprintf('Incompatible use of dynamic environment variables "%s" found in parameters.', implode('", "', $usedEnvs)), 0, $previous);
    }
}
