<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Dumper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\ContextProvider\SourceContextProvider;
use Symfony\Component\VarDumper\Dumper\ContextualizedDumper;

/**
 * @author Kévin Thérage <therage.kevin@gmail.com>
 */
class ContextualizedDumperTest extends TestCase
{
    public function testContextualizedCliDumper()
    {
        $wrappedDumper = new CliDumper('php://output');
        $wrappedDumper->setColors(true);

        $var = 'example';
        $href = \sprintf('file://%s#L%s', __FILE__, 37);
        $dumper = new ContextualizedDumper($wrappedDumper, [new SourceContextProvider()]);
        $cloner = new VarCloner();
        $data = $cloner->cloneVar($var);

        ob_start();
        $dumper->dump($data);
        $out = ob_get_clean();

        $this->assertStringContainsString("\e]8;;{$href}\e\\^\e]", $out);
        $this->assertStringContainsString("m{$var}\e[", $out);
    }
}
