<?php

namespace PHPUnit\Framework\Error;

if (class_exists('\PHPUnit_Framework_Error_Warning')) {
    class Warning extends \PHPUnit_Framework_Error_Warning
    {
    }
}
