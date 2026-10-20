<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

/**
 * @template T
 * @template-covariant TObject of object
 *
 * @method TObject virtualMethod()
 */
interface TemplatedReturnTypeInterface
{
    /**
     * @return T
     */
    public function classTemplate();

    /**
     * @return TObject|null
     */
    public function nullableClassTemplate();

    /**
     * @template TMethod
     *
     * @param TMethod $value
     *
     * @return TMethod
     */
    public function methodTemplate($value);

    /**
     * @return T[]
     */
    public function arrayOfTemplates();

    /**
     * @return string
     */
    public function notTemplated();
}
