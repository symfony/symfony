CssSelector Component
=====================

CssSelector converts CSS selectors to XPath expressions.

The component only goal is to convert CSS selectors to their XPath
equivalents:

```php
use Symfony\Component\CssSelector\CssSelectorConverter;

$converter = new CssSelectorConverter();
print $converter->toXPath('div.item > h4 > a');
```

HTML and XML are different
--------------------------

The `CssSelector` component comes with an `HTML` extension which is enabled by
default. If you need to use this component with `XML` documents, you have to
disable this `HTML` extension. That's because, `HTML` tag & attribute names are
always lower-cased, but case-sensitive in `XML`:

```php
// disable `HTML` extension:
$converter = new CssSelectorConverter(false);
```

When the `HTML` extension is enabled, tag names are lower-cased, attribute
names are lower-cased, the following extra pseudo-classes are supported:
`checked`, `link`, `disabled`, `enabled`, `selected`, `invalid`, `hover`,
`visited`, and the `lang()` function is also added.

Resources
---------

This component is a port of the Python cssselect library
[v0.7.1](https://github.com/SimonSapin/cssselect/releases/tag/v0.7.1),
which is distributed under the BSD license.

You can run the unit tests with the following command:

    $ cd path/to/Symfony/Component/CssSelector/
    $ composer install
    $ phpunit

License
-------

This component is a port of the Python cssselect library,
which is copyright Ian Bicking, https://github.com/SimonSapin/cssselect.

Copyright (c) 2007-2012 Ian Bicking and contributors. See AUTHORS
for more details.

All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

1. Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.

2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in
the documentation and/or other materials provided with the
distribution.

3. Neither the name of Ian Bicking nor the names of its contributors may
be used to endorse or promote products derived from this software
without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL IAN BICKING OR
CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
