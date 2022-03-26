<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\FlashMessage;

use Symfony\Component\Notifier\Exception\FlashMessageImportanceMapperException;

/**
 * @author Ben Roberts <ben@headsnet.com>
 */
interface FlashMessageImportanceMapperInterface
{
    /**
     * @throws FlashMessageImportanceMapperException
     */
    public function flashMessageTypeFromImportance(string $importance): string;
}
