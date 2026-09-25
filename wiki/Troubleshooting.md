# Troubleshooting

Start with the CLI: it checks the token and the webhook in seconds.

```bash
vendor/bin/tgbot bot:info       # is the token valid?
vendor/bin/tgbot webhook:info   # is a webhook set? what was the last delivery error?
```

## The bot does not answer

| Check | Fix |
|---|---|
| `bot:info` fails with 401 | The token is wrong or was revoked: copy it again from @BotFather |
| Long polling fails with **409 Conflict** | A webhook is still set: `vendor/bin/tgbot webhook:delete` |
| `webhook:info` shows a delivery error | See [Webhooks](#webhooks) below |
| No handler matches | Add `$bot->onUpdate('message', ...)` or a `fallback()` to see what arrives |
| An exception is swallowed | Add `$bot->onError(fn(Throwable $e) => error_log((string) $e))` |
| In a group, only commands arrive | Privacy mode: the bot only sees commands and replies. Disable it with `/setprivacy` in @BotFather, then remove and re-add the bot |
| `/start@other_bot` or `/start@your_bot` is ignored | With webhooks, set the username: `$bot->setUsername('your_bot')` |

## Webhooks

| `webhook:info` says | Cause |
|---|---|
| `Wrong response from the webhook: 403 Forbidden` | The secret registered with `webhook:set --secret` differs from `TELEGRAM_SECRET_TOKEN` on the server |
| `Wrong response from the webhook: 500` | A PHP fatal error: check the web server's error log |
| `Wrong response from the webhook: 404` | The URL does not reach `webhook.php` |
| `SSL error` | The certificate is invalid, self-signed without being uploaded, or does not match the domain |
| `Connection timed out` | A firewall blocks Telegram, or the port is not 443, 80, 88 or 8443 |

`pending_update_count` grows while the webhook fails. After fixing it, `webhook:set --drop-pending` discards the backlog if you do not want to process it.

## Conversations forget the state

With webhooks, every update is a new PHP process: an `ArrayCache` starts empty each time. Use a `FileCache` or a Redis-backed cache (see [Conversations](Conversations#where-states-are-stored)).

## Telegram errors

| Error | Meaning |
|---|---|
| `Bad Request: can't parse entities` | The HTML is invalid. Escape user text with `Formatter::escape()` |
| `Bad Request: message is not modified` | An edit with the same content. `edit()` returns `false` instead of throwing |
| `Bad Request: chat not found` | Wrong chat id, or the user never started the bot |
| `Bad Request: message to edit not found` | The message was deleted, or the id belongs to another chat |
| `Bad Request: query is too old` | A callback or inline query answered after about 15 seconds: answer first, then work |
| `Forbidden: bot was blocked by the user` | Nothing to fix: stop writing to that user |
| `Forbidden: bot is not a member of the channel chat` | Add the bot to the channel as an administrator |
| `Too Many Requests: retry after N` | Flood control: send fewer messages; see [retries](Configuration#retries) |
| `Bad Request: group chat was upgraded to a supergroup chat` | Use `$e->getMigrateToChatId()` as the new chat id |

## Uploads and downloads

| Problem | Fix |
|---|---|
| A path is sent as text, or Telegram says `wrong file identifier` | Wrap local files: `InputFile::fromPath($path)` |
| `File not found or not readable` | Check the path and the permissions of the PHP user |
| `File is too large to load in memory` | Pass a destination: `$bot->downloadFile($fileId, '/path/file')` |
| `file is too big` from Telegram | Bots download up to 20 MB and upload up to 50 MB; a [local Bot API server](Configuration#local-bot-api-server) raises the limits |

## Debugging

Log every request and response:

```php
$bot = new Bot(new Config(token: $token, debug: '/tmp/bot-debug.log'));
```

Turn it off afterwards: the log contains messages.
