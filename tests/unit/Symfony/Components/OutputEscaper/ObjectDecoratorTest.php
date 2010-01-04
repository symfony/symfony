<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../bootstrap.php';

use Symfony\Components\OutputEscaper\Escaper;

$t = new LimeTest(3);

class OutputEscaperTest
{
  public function __toString()
  {
    return $this->getTitle();
  }

  public function getTitle()
  {
    return '<strong>escaped!</strong>';
  }

  public function getTitles()
  {
    return array(1, 2, '<strong>escaped!</strong>');
  }
}

$object = new OutputEscaperTest();
$escaped = Escaper::escape('esc_entities', $object);

$t->is($escaped->getTitle(), '&lt;strong&gt;escaped!&lt;/strong&gt;', 'The escaped object behaves like the real object');

$array = $escaped->getTitles();
$t->is($array[2], '&lt;strong&gt;escaped!&lt;/strong&gt;', 'The escaped object behaves like the real object');

// __toString()
$t->diag('__toString()');

$t->is($escaped->__toString(), '&lt;strong&gt;escaped!&lt;/strong&gt;', 'The escaped object behaves like the real object');
