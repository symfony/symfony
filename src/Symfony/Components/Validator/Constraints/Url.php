<?php

namespace Symfony\Components\Validator\Constraints;

class Url extends \Symfony\Components\Validator\Constraint
{
    public $message = 'Symfony.Validator.Url.message';
    public $protocols = array('http', 'https', 'ftp', 'ftps');
}