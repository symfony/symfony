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

class Deserialization extends SerializerAction
{
    public $type;

    public function __construct($data, $result, string $type, string $format, array $context = [])
    {
        parent::__construct($data, $result, $format, $context);
        $this->type = $type;
    }
}
