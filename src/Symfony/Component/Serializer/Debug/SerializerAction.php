<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Debug;

abstract class SerializerAction
{
    /**
     * @var object|string
     */
    public $data;
    public $format;
    public $context;
    /**
     * @var object|string
     */
    public $result;

    public function __construct($data, $result, string $format, array $context = [])
    {
        $this->data = $data;
        $this->result = $result;
        $this->format = $format;
        $this->context = $context;
    }
}
