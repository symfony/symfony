<?php

namespace Symfony\Component\Console\Tests\Helper;

/**
 * @author Janusz Jablonski <januszjablonski.pl@gmail.com>
 */
class FakeStream
{
    /** @var array */
    private $values = array();

    public static function register($name = 'fake')
    {
        stream_wrapper_register($name, 'Symfony\Component\Console\Tests\Helper\FakeStream');
    }

    public static function unregister($name = 'fake')
    {
        stream_wrapper_unregister($name);
    }

    public function stream_open()
    {
        return true;
    }

    public function stream_read()
    {
        $value = array_shift($this->values);

        return $value;
    }

    public function stream_write($values)
    {
        $this->values = explode(';', $values);

        return strlen($values);
    }

    public function stream_eof()
    {
        return !0 < count($this->values);
    }

}
