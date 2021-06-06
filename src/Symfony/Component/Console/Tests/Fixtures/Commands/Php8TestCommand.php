<?php

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(name: 'foo', description: 'desc', hidden: true, aliases: ['f'])]
class Php8Command1 extends Command
{
}

#[AsCommand(name: 'foo')]
class Php8Command2 extends Command
{
}

#[AsCommand(name: 'foo', description: 'desc')]
class Php8Command3 extends Command
{
}

#[AsCommand(name: 'foo', description: 'desc', aliases: ['f'])]
class Php8Command4 extends Command
{
}

#[AsCommand(name: 'foo', description: 'desc', aliases: ['f'], hidden: true)]
class Php8Command5 extends Command
{
}
