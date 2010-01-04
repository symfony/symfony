<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../bootstrap.php';

use Symfony\Components\YAML\YAML;
use Symfony\Components\YAML\Parser;

YAML::setSpecVersion('1.1');

$t = new LimeTest(148);

$parser = new Parser();

$path = __DIR__.'/../../../../fixtures/Symfony/Components/YAML';
$files = $parser->parse(file_get_contents($path.'/index.yml'));
foreach ($files as $file)
{
  $t->diag($file);

  $yamls = file_get_contents($path.'/'.$file.'.yml');

  // split YAMLs documents
  foreach (preg_split('/^---( %YAML\:1\.0)?/m', $yamls) as $yaml)
  {
    if (!$yaml)
    {
      continue;
    }

    $test = $parser->parse($yaml);
    if (isset($test['todo']) && $test['todo'])
    {
      $t->todo($test['test']);
    }
    else
    {
      $expected = var_export(eval('return '.trim($test['php']).';'), true);

      $t->is(var_export($parser->parse($test['yaml']), true), $expected, $test['test']);
    }
  }
}

// test tabs in YAML
$yamls = array(
  "foo:\n	bar",
  "foo:\n 	bar",
  "foo:\n	 bar",
  "foo:\n 	 bar",
);

foreach ($yamls as $yaml)
{
  try
  {
    $content = $parser->parse($yaml);
    $t->fail('YAML files must not contain tabs');
  }
  catch (InvalidArgumentException $e)
  {
    $t->pass('YAML files must not contain tabs');
  }
}

// objects
$t->diag('Objects support');
class A
{
  public $a = 'foo';
}
$a = array('foo' => new A(), 'bar' => 1);
$t->is($parser->parse(<<<EOF
foo: !!php/object:O:1:"A":1:{s:1:"a";s:3:"foo";}
bar: 1
EOF
), $a, '->parse() is able to dump objects');
