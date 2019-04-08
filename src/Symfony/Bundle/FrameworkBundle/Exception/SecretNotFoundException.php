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

class SecretNotFoundException extends \RuntimeException
{
    private $name;

    public function __construct(string $name)
    {
        $this->name = $name;
        parent::__construct(sprintf('The secret "%s" does not exist.', $name));
    }

    public function getName(): string
    {
        return $this->name;
    }
}
