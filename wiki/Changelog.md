# Changelog

The complete history, with migration notes, is in [CHANGELOG.md](https://github.com/LightYagami28/TGbotPHP/blob/main/CHANGELOG.md). Releases are published on the [releases page](https://github.com/LightYagami28/TGbotPHP/releases).

## 3.0.0

- **PHP 8.4** or later.
- **All of Bot API 10.3**: 185 methods and 27 update types, checked against the official documentation.
- **Fixed**: methods returning `True` or integers threw a `TypeError`; uploads were broken; commands did not match; Telegram's error descriptions were lost; `answerCallbackQuery` was missing; numeric callback data broke routing; concurrent webhook requests lost conversation data; `reply()` failed in business chats and channel direct messages.
- **New**: webhook handling with secret token and long polling with backoff; routing for text patterns, callbacks, inline queries and any update type; conversations; middleware that can wrap handlers; `InputFile`; streamed downloads; `BotTester` for testing bots offline; Ed25519 validation of Mini App data; the `tgbot` CLI commands.
- **Quality**: PHPStan level 10 with strict rules, 97% line coverage, end-to-end tests against the real API, CI on PHP 8.4, 8.5 and 8.6.
- **Breaking changes**: see [Upgrading from 2.x](Upgrading-from-2.x).

## 2.0.0

Rewrote the single-file `botTG` class as a Composer package, with one trait per API area. Many methods did not work: upgrade to 3.0.

## 1.0.0

The original single-file `botTG` class.
