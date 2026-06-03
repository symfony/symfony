<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures\FinalConstant;

interface FinalConstantsInterface
{
    /**
     * @final
     */
    public const OVERRIDDEN_FINAL_INTERFACE = 'OVERRIDDEN_FINAL_INTERFACE';

    public const OVERRIDDEN_NOT_FINAL_INTERFACE = 'OVERRIDDEN_NOT_FINAL_INTERFACE';

    /**
     * @final
     */
    public const NOT_OVERRIDDEN_FINAL_INTERFACE = 'NOT_OVERRIDDEN_FINAL_INTERFACE';

    public const NOT_OVERRIDDEN_NOT_FINAL_INTERFACE = 'NOT_OVERRIDDEN_NOT_FINAL_INTERFACE';
}
