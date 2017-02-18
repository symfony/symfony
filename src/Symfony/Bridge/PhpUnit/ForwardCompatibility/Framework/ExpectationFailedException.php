<?php

namespace PHPUnit\TextUI;

if (class_exists('\PHPUnit_Framework_ExpectationFailedException')) {
    class ExpectationFailedException extends \PHPUnit_Framework_ExpectationFailedException
    {
    }
}
