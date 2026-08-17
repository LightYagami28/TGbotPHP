# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-08-17

### ✨ Major Release - Secure by Default

**Complete rewrite with security hardening and PHP 8.4 features.**

### Security Fixes ✅
- ✅ HTTPS enforcement integrated
- ✅ Path traversal protection (directory validation)
- ✅ XSS protection (output escaping)
- ✅ JSON injection prevention (strict parsing)
- ✅ Webhook secret token verification (hash_equals)
- ✅ Telegram IP anti-spoofing validation
- ✅ File resource leak fixed
- ✅ SSL/TLS certificate verification
- ✅ All OWASP vulnerabilities addressed

### Added
- PHP 8.4+ features (typed properties, named arguments, match expressions, null-safe operators)
- Webhook signature verification support
- HTTPS enforcement at class level
- Input validation and sanitization
- Output escaping and template safety
- Comprehensive error handling
- File path validation for media
- URL validation for links
- Complete security documentation

## [1.0.0] - 2024

### Added
- Core `botTG` class for Telegram bot creation
- Support for webhook-based updates
- Inline keyboard builder
- Link button keyboard builder
- Keyboard merging utilities
- Command parsing and handling
- Callback query responses
- Message editing capabilities
- Photo message support
- Text templating with placeholders
- Debug mode
- IP validation for Telegram servers
- Message forwarding
- Private chat detection

### Features
- `command_simple()` - Handle simple text commands
- `simple_callback_response()` - Handle button clicks
- `send_message()` - Send messages with optional photo and keyboard
- `edit_message()` - Edit existing messages
- `build_keyboard_of_inline()` - Create inline button keyboards
- `build_keyboard_of_links()` - Create link button keyboards
- `merge_keyboards()` - Merge two keyboards
- `merge_multiple_keyboards()` - Merge multiple keyboards
- `forward_message()` - Forward messages between chats
- `forward_message_from_reply()` - Forward from reply
- `get_text_message()` - Extract message text
- `get_data()` - Extract callback data
- `get_chat_id()` - Get chat ID
- `get_message_id()` - Get message ID
- `check_text_message()` - Check if message matches
- `check_callbackquery_data()` - Check if callback matches
- `is_private()` - Check if in private chat
- `checkIp()` - Validate Telegram IP

### Examples
- `botesempio.php` - Feature-complete example bot
- `echobot.php` - Simple echo bot with IP validation

### Known Issues
- ❌ Token exposure via GET parameter in examples
- ❌ No HTTPS enforcement
- ❌ Path traversal vulnerability in photo handling
- ❌ Insufficient input validation
- ❌ File resource leak (line 400)
- ❌ No rate limiting
- ❌ No security headers

## Version History

### Development Status
This library is actively developed and marked as "W.I.P. (Work in Progress)" in original documentation.

- Ready for small projects
- Actively maintained and updated
- Use in production with caution (see SECURITY.md)

---

## Upgrade Guide

### From Version < 1.0.0

If you're upgrading from earlier versions:

1. Review SECURITY.md for critical issues
2. Update token handling (don't use GET parameter)
3. Add HTTPS enforcement to webhook
4. Implement input validation
5. Consider implementing rate limiting
6. Update error handling

## Future Roadmap

### Version 1.1.0 (Planned)
- [ ] Fix file resource leak
- [ ] Add security headers
- [ ] Implement webhook signature validation
- [ ] Add rate limiting utilities
- [ ] Improve error handling
- [ ] Add inline query support

### Version 1.2.0 (Planned)
- [ ] Add more Telegram Bot API methods
- [ ] Support for inline mode
- [ ] Payment handling
- [ ] Game scores
- [ ] Sticker pack management

### Version 2.0.0 (Future)
- [ ] Namespace support
- [ ] PSR-4 autoloading
- [ ] Async request support
- [ ] Improved type hinting
- [ ] Event-based architecture

## Deprecations

Currently no deprecations.

## Security

For security vulnerabilities, see [SECURITY.md](SECURITY.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

---

**Note:** This changelog is maintained starting from version 1.0.0. Earlier development history may be incomplete.
