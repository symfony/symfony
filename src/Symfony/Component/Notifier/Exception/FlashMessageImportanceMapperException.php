<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Exception;

/**
 * @author Ben Roberts <ben@headsnet.com>
 */
class FlashMessageImportanceMapperException extends LogicException
{
    public function __construct(string $importance, string $mappingClass)
    {
        $message = \sprintf('The "%s" Notifier flash message mapper does not support an importance value of "%s".', $mappingClass, $importance);

        parent::__construct($message);
    }
}
