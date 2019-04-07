<?php

namespace Symfony\Component\Debug\Tests\Fixtures\DiscourageSerializable;

/**
 * @method void __unserialize(array $data)
 */
interface ExtendsSerializable extends \Serializable
{
}
