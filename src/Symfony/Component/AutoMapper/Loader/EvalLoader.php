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
 * Use a generator and eval to requiring mapping of a class.
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
    public function loadClass(MapperGeneratorMetadataInterface $mapperConfiguration): void
    {
        $class = $this->generator->compile($mapperConfiguration);

        eval($this->printer->prettyPrint([$class]));
    }
}
