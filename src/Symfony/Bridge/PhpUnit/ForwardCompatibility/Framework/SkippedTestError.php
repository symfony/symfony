<?php

namespace PHPUnit\TextUI;

if (class_exists('\PHPUnit_Framework_SkippedTestError')) {
    class SkippedTestError extends \PHPUnit_Framework_SkippedTestError
    {
    }
}
