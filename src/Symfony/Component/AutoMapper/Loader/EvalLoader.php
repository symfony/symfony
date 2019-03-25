<?php
/**
 * Created by IntelliJ IDEA.
 * User: joelwurtz
 * Date: 2/14/19
 * Time: 2:07 PM.
 */

namespace Symfony\Component\AutoMapper\Loader;

use PhpParser\PrettyPrinter\Standard;
use Symfony\Component\AutoMapper\Generator\Generator;
use Symfony\Component\AutoMapper\MapperGeneratorMetadataInterface;

/**
 * Uses a generator and eval to requiring mapping of a class.
 */
class EvalLoader implements ClassLoaderInterface
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
