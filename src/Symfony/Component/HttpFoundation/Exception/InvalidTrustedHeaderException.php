<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Exception;

final class InvalidTrustedHeaderException extends \InvalidArgumentException implements ExceptionInterface
{
    /**
     * @var string
     */
    private $header;

    /**
     * @var string
     */
    private $value;

    /**
     * @param string      $header
     * @param string|null $value
     * @param string      $message
     */
    public function __construct($header, $value, $message)
    {
        parent::__construct($message);
        $this->header = $header;
        $this->value = $value;
    }
}
