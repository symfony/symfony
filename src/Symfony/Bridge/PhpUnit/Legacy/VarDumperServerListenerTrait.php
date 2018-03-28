<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Legacy;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\VarDumperServerListener;
use Symfony\Component\VarDumper\Dumper\ServerDumper;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 *
 * @internal
 */
class VarDumperServerListenerTrait
{
    /** @var TestCase|null */
    private $currentTestCase;

    public function __construct(string $host = null)
    {
        if (!class_exists(ServerDumper::class)) {
            throw new \LogicException(sprintf('The "%s" class is required for using the "%s" listener. Install "symfony/var-dumper" version 4.1 or above.', ServerDumper::class, VarDumperServerListener::class));
        }

        ServerDumper::register($host, true, array('phpunit' => $this));
    }

    public function startTest($test)
    {
        if (!$test instanceof TestCase) {
            $this->currentTestCase = null;

            return;
        }

        $this->currentTestCase = $test;
    }

    public function getContext(): ?array
    {
        if (!$this->currentTestCase) {
            return null;
        }

        return array(
            'identifier' => spl_object_hash($this->currentTestCase),
            'test_class' => \get_class($this->currentTestCase),
            'test_case' => $this->currentTestCase->getName(),
        );
    }
}
