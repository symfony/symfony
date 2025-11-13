CHANGELOG
=========

7.4
---

 * Use the native HTML5 parser when using PHP 8.4+
 * Deprecate `MastermindsParser`; use `NativeParser` instead
 * [BC BREAK] `ParserInterface::parse()` can now return `\Dom\Node|\DOMNode|null` instead of just `\DOMNode|null`
 * Add argument `$context` to `ParserInterface::parse()`

7.2
---

 * Add support for configuring the default action to block or allow unconfigured elements instead of dropping them

6.4
---

 * Add support for sanitizing unlimited length of HTML document

6.1
---

 * Add the component as experimental
