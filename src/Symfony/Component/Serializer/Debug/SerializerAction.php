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
     * @var mixed
     */
    public $data;
    /**
     * @var string
     */
    public $format;
    /**
     * @var array
     */
    public $context;
    /**
     * @var mixed
     */
    public $result;

    public function __construct($data, string $format, array $context = [])
    {
        $this->data = $data;
        $this->format = $format;
        $this->context = $context;
    }
}
