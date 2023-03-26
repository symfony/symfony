CHANGELOG
=========

6.3
---

 * Support detection of related parts if `Content-Id` is used instead of the name
 * Add `TextPart::getDispostion()`

6.2
---

 * Add `File`
 * Deprecate `Email::attachPart()`, use `addPart()` instead
 * Deprecate calling `Message::setBody()` without arguments

6.1
---

 * Add `DataPart::getFilename()` and `DataPart::getContentType()`

6.0
---

 * Remove `Address::fromString()`, use `Address::create()` instead
 * Remove `Serializable` interface from `RawMessage`

5.2.0
-----

 * Add support for DKIM
 * Deprecated `Address::fromString()`, use `Address::create()` instead

4.4.0
-----

 * [BC BREAK] Removed `NamedAddress` (`Address` now supports a name)
 * Added PHPUnit constraints
 * Added `AbstractPart::asDebugString()`
 * Added `Address::fromString()`

4.3.3
-----

 * [BC BREAK] Renamed method `Headers::getAll()` to `Headers::all()`.

4.3.0
-----

 * Introduced the component as experimental
