<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Dumper;

use Symfony\Component\VarDumper\Cloner\Data;

/**
 * A dumper decorator to return the dump as string.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class ToStringDumper implements DataDumperInterface
{
    private $dumper;

    public function __construct(AbstractDumper $dumper)
    {
        $this->dumper = $dumper;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function dump(Data $data)
    {
        $dump = fopen('php://memory', 'r+b');
        $prevOutput = $this->dumper->setOutput($dump);

        $this->dumper->dump($data);

        $this->dumper->setOutput($prevOutput);
        rewind($dump);

        return stream_get_contents($dump);
    }
}
