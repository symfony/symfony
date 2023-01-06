<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures\FinalConstant;

interface FinalConstantsInterface2 extends FinalConstantsInterface
{
    /**
     * @final for whatever reason
     */
    public const OVERRIDDEN_FINAL_INTERFACE_2 = 'OVERRIDDEN_FINAL_INTERFACE_2';

    public const OVERRIDDEN_NOT_FINAL_INTERFACE_2 = 'OVERRIDDEN_NOT_FINAL_INTERFACE_2';

    /**
     * @final
     */
    public const NOT_OVERRIDDEN_FINAL_INTERFACE_2 = 'NOT_OVERRIDDEN_FINAL_INTERFACE_2';

    public const NOT_OVERRIDDEN_NOT_FINAL_INTERFACE_2 = 'NOT_OVERRIDDEN_NOT_FINAL_INTERFACE_2';
}
