CHANGELOG
=========

6.3
---

 * Allow to update Slack messages

6.0
---

 * Remove `SlackOptions::channel()`, use `SlackOptions::recipient()` instead

5.3
---

 * The bridge is not marked as `@experimental` anymore
 * Check for maximum number of buttons in Slack action block
 * Add HeaderBlock
 * Slack access tokens needs to start with "xox" (see https://api.slack.com/authentication/token-types)
 * Add `SlackOptions::threadTs()` to send message as reply

5.2.0
-----

 * [BC BREAK] Reverted the 5.1 change and use the Slack Web API again (same as 5.0)

5.1.0
-----

 * [BC BREAK] Change API endpoint to use the Slack Incoming Webhooks API

5.0.0
-----

 * Added the bridge
