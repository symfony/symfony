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

class BadServiceClassException extends RuntimeException
{
    public function __construct($id, $className, $key)
    {
        parent::__construct(
            sprintf(
                'Class "%s" not found. Check the spelling on the "%s" configuration for your "%s" service.',
                $className,
                $key,
                $id
            )
        );
    }
}
