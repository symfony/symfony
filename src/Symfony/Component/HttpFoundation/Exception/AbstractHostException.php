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

/**
 * THe HTTP request contains incorrect host data.
 *
 * @author SpacePossum
 */
abstract class AbstractHostException extends \UnexpectedValueException implements ExceptionInterface
{
    /**
     * @var string
     */
    private $host;

    /**
     * @param string $host
     * @param string $message
     */
    public function __construct($host, $message)
    {
        parent::__construct($message);
        $this->host = $host;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }
}
