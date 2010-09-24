<?php

namespace Symfony\Component\Validator\Constraints;

class Url extends \Symfony\Component\Validator\Constraint
{
    public $message = 'This value is not a valid URL';
    public $protocols = array('http', 'https', 'ftp', 'ftps');
}