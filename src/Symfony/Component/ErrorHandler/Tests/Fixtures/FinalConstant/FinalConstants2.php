<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures\FinalConstant;

class FinalConstants2 {
    /**
     * @final
     */
    public const OVERRIDDEN_FINAL_PARENT_PARENT_CLASS = 'OVERRIDDEN_FINAL_PARENT_PARENT_CLASS';

    public const OVERRIDDEN_NOT_FINAL_PARENT_PARENT_CLASS = 'OVERRIDDEN_NOT_FINAL_PARENT_PARENT_CLASS';

    /**
     * @final
     */
    public const NOT_OVERRIDDEN_FINAL_PARENT_PARENT_CLASS = 'NOT_OVERRIDDEN_FINAL_PARENT_PARENT_CLASS';

    public const NOT_OVERRIDDEN_NOT_FINAL_PARENT_PARENT_CLASS = 'NOT_OVERRIDDEN_NOT_FINAL_PARENT_PARENT_CLASS';
}
