<?php 
namespace Namespaced
{
class WithComments
{
public static $loaded = true;
}
$string ='string should not be   modified {$string}';
$heredoc = (<<<HD


Heredoc should not be   modified {$string}


HD
);
$nowdoc =<<<'ND'


Nowdoc should not be   modified {$string}


ND
;
}
namespace
{
class Pearlike_WithComments
{
public static $loaded = true;
}
}
namespace {require __DIR__.'/Fixtures/Namespaced/WithDirMagic.php';}
namespace {require __DIR__.'/Fixtures/Namespaced/WithFileMagic.php';}
namespace {require __DIR__.'/Fixtures/Namespaced/WithHaltCompiler.php';}
namespace {require __DIR__.'/Fixtures/Namespaced/WithStrictTypes.php';}