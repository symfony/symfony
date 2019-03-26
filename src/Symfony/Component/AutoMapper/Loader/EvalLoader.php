<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Loader;

use PhpParser\PrettyPrinter\Standard;
use Symfony\Component\AutoMapper\Generator\Generator;
use Symfony\Component\AutoMapper\MapperGeneratorMetadataInterface;

/**
 * Use eval to load mappers.
 *
 * @expiremental in 4.3
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
final class EvalLoader implements ClassLoaderInterface
{
    private $generator;

    private $printer;

    public function __construct(Generator $generator)
    {
        $this->generator = $generator;
        $this->printer = new Standard();
    }

    /**
     * {@inheritdoc}
     */
    public function loadClass(MapperGeneratorMetadataInterface $mapperGeneratorMetadata): void
    {
        $class = $this->generator->generate($mapperGeneratorMetadata);

        eval($this->printer->prettyPrint([$class]));
    }
}
