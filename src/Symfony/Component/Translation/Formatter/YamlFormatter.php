<?php

namespace Symfony\Component\Translation\Formatter;

use \Symfony\Component\Yaml\Yaml;

class YamlFormatter implements FormatterInterface
{
    public function format(array $messages)
    {
         return Yaml::dump($messages);
    }
}