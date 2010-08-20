<?php

namespace Symfony\Component\Validator\Constraints;

class Url extends \Symfony\Component\Validator\Constraint
{
    public $message = 'Symfony.Validator.Url.message';
    public $protocols = array('http', 'https', 'ftp', 'ftps');
}