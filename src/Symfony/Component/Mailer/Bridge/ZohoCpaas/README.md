Zoho CPaaS Bridge
=================

Provides [Zoho CPaaS](https://www.zoho.com/cpaas/) (formerly ZeptoMail) integration for Symfony Mailer.

Configuration example:

```env
# API
MAILER_DSN=zohocpaas+api://API_KEY@default

# same thing, shorter
MAILER_DSN=zohocpaas://API_KEY@default

# an account in another data centre: the API host of your Agent
MAILER_DSN=zohocpaas+api://API_KEY@cpaas.zoho.eu

# SMTP, port 587 with STARTTLS
MAILER_DSN=zohocpaas+smtp://USERNAME:PASSWORD@default

# SMTP, port 465 with implicit TLS, on the SMTP server of your Agent
MAILER_DSN=zohocpaas+smtps://USERNAME:PASSWORD@smtp.zeptomail.eu
```

where:
 - `API_KEY` is the Agent's API key (Agents > your Agent > SMTP/API); the bridge adds the
   `Zoho-enczapikey` prefix of the `Authorization` header itself
 - `USERNAME` and `PASSWORD` are the SMTP credentials of the Agent's SMTP tab; the username is
   `emailapikey`, or the From address or a generated username with a shorter password
 - the host in place of `default` is the one your Agent shows: the API host (`cpaas.zoho.eu`) on
   the API tab and the server name (`smtp.zeptomail.eu`) on the SMTP tab. It depends on the data
   centre of the account, and so does your API key, so outside the US data centre the host is not
   optional. `default` stands for `cpaas.zoho.com` and `smtp.zeptomail.com`

The scheme decides the SMTP port, as Zoho documents it: `zohocpaas+smtp` is port 587 with STARTTLS
and `zohocpaas+smtps` port 465 with TLS from the start. A port in the DSN replaces it.
`zohocpaas+smtps` always uses TLS from the start. With `zohocpaas+smtp`, port 465 uses TLS from the
start too, and any other port uses STARTTLS.

The API transport carries the sender, recipients, reply-to addresses, subject, text and HTML
bodies, attachments, inline images (referenced by their `cid`) and custom headers. A failed send
reports the error codes returned by the API, which tell apart, for example, an unverified sender
domain from exhausted credits.

Open and click tracking follow the `TrackingHeader` on both transports, as `track_opens` and
`track_clicks` on the API and as the `X-TM-OPEN-TRACK` and `X-TM-CLICK-TRACK` headers on SMTP;
without it, the Agent's settings apply.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
