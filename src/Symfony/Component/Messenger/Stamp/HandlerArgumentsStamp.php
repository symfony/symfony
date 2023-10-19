<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

/**
 * @author Jáchym Toušek <enumag@gmail.com>
 */
final class HandlerArgumentsStamp implements NonSendableStampInterface
{
    public function __construct(
        private array $additionalArguments,
    ) {
    }

    /**
     * @return array
     */
    public function getAdditionalArguments()
    {
        return $this->additionalArguments;
    }
}
