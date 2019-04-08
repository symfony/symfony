<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Exception;

class EncryptionKeyNotFoundException extends \RuntimeException
{
    private $keyLocation;

    public function __construct(string $keyLocation)
    {
        $this->keyLocation = $keyLocation;
        parent::__construct(sprintf('Encryption key not found in "%s".', $keyLocation));
    }

    public function getKeyLocation(): string
    {
        return $this->keyLocation;
    }
}
