<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

/**
 * @template T
 * @template-covariant TObject of object
 * @phpstan-template TPhpStan
 * @psalm-template-covariant TPsalm of object
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
     * @return TPhpStan
     */
    public function phpStanClassTemplate();

    /**
     * @return TPsalm|null
     */
    public function psalmClassTemplate();

    /**
     * @psalm-template TMethod
     *
     * @param TMethod $value
     *
     * @return TMethod
     */
    public function psalmMethodTemplate($value);

    /**
     * @return T[]
     */
    public function arrayOfTemplates();

    /**
     * @return string
     */
    public function notTemplated();
}
