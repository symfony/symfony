<?php

namespace A {
    class Property {

    }

    class Dummy {
        /**
         * @var Property
         */
        public $property;
    }
}

namespace B {
    class Dummy extends \A\Dummy {

    }
}
