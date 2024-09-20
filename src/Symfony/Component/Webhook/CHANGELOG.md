CHANGELOG
=========

7.2
---

 * Make `AbstractRequestParserTestCase` compatible with PHPUnit 10+
 * Add `PayloadSerializerInterface` with implementations to decouple the remote event handling from the Serializer component
 * Add optional `$request` argument to `RequestParserInterface::createSuccessfulResponse()` and `RequestParserInterface::createRejectedResponse()`

6.4
---

 * Mark the component as non experimental

6.3
---

 * Add the component (experimental)
