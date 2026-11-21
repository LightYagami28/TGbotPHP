---
title: "NOTICE"
description: "Note legali, titolarità, licenza e riferimenti alla Telegram Bot
API."
---

## NOTICE — Note legali e licenze

L'indice della documentazione è in [docs/README.md](docs/README.md), il piano di
lavoro in [ROADMAP.md](ROADMAP.md).

## 1. Titolarità del software

- **Autore e manutentore**: Alessandro Maule (Light Yagami).
- **Licenza**: Apache License, Version 2.0. Il testo integrale è in
[LICENSE](LICENSE). La licenza dichiarata in `composer.json` è `Apache-2.0`.

## 2. Riferimenti alla Telegram Bot API

L'implementazione del client si basa sulle specifiche ufficiali della Telegram
Bot API (`https://core.telegram.org/bots/api`). Questo progetto non è affiliato
né approvato da Telegram FZ-LLC.

## 3. Requisiti e dipendenze

- PHP 8.5 o successivo, estensioni `curl` e `json`; `sodium` è opzionale per la
verifica Ed25519 dei Mini App.
- Nessuna dipendenza runtime di terze parti. Le dipendenze di sviluppo (PHPUnit,
PHPStan) sono elencate in `composer.json` e bloccate con checksum in
`composer.lock`.

## 4. Fork

Il progetto è un fork di
[OpenTelegramFiles/TGbotPHP](https://github.com/OpenTelegramFiles/TGbotPHP),
distribuito sotto Apache License 2.0.
