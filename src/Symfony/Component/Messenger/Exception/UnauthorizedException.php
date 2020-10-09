<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Exception;

/**
 * @author Maxime Perrimond <max.perrimond@gmail.com>
 */
class UnauthorizedException extends RuntimeException
{
    private $attribute;
    private $subject;

    public function __construct(string $attribute, $subject)
    {
        parent::__construct(sprintf('Message of type "%s" with attribute "%s" is unauthorized.', \get_class($subject), $attribute));

        $this->attribute = $attribute;
        $this->subject = $subject;
    }

    public function getAttribute(): string
    {
        return $this->attribute;
    }

    public function getSubject()
    {
        return $this->subject;
    }
}
