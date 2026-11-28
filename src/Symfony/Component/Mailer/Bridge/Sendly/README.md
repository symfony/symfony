Sendly Bridge
=============

Provides Sendly integration for Symfony Mailer.

Configuration example:

```env
# API
MAILER_DSN=sendly+api://API_KEY@default

# same thing, shorter
MAILER_DSN=sendly://API_KEY@default
```

where `API_KEY` is your Sendly API key (a `pk_` key with the `emails:send` scope is enough). The
`From` address must belong to a domain verified in your Sendly project.

Sendly sends through its HTTP API, so the bridge is API-only and `sendly` is an alias for
`sendly+api`. A failed send reports the error message returned by the API.

The API differs from most mail APIs in two places:

 * It takes a single `body`. The transport sends the HTML part when the email has one and the
   text part otherwise; when both are set, the text part is not sent.
 * It takes exactly one `to` address per message. An email with several recipients is sent as one
   request per recipient, in order. Cc and Bcc are attached to the first request only, so they
   receive a single copy. If a later request fails, the earlier ones have already been accepted.

The transport carries the sender (with display name), recipients, subject, a single reply-to
address, attachments (including inline `cid:` images), custom headers and tags set with
`Symfony\Component\Mailer\Header\TagHeader`. Headers that Sendly writes itself (`Sender`,
`Return-Path`, `List-Unsubscribe`, `X-Sendly-*` and so on) are not forwarded, because the API
refuses them. Sendly has no metadata field, so `MetadataHeader` values are not sent. An email with
several reply-to addresses is refused rather than keeping only one.

A message can have at most 50 recipients (To, Cc and Bcc together) and 10 attachments, and the API
refuses a larger one. Open and click tracking is a project setting in Sendly, not a per-message
option, so `TrackingHeader` is not supported.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
