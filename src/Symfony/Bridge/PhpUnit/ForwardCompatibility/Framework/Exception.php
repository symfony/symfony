<?php

namespace PHPUnit\TextUI;

if (class_exists('\PHPUnit_Framework_Exception')) {
    class Exception extends \PHPUnit_Framework_Exception
    {
    }
}
