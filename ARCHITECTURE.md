---
title: "Architecture"
description: "Modules, the request and update flow, and the design choices of TGbotPHP."
---

# Architettura

> L'indice della documentazione è in [docs/architecture.md](docs/architecture.md), il piano di lavoro in [ROADMAP.md](ROADMAP.md).

`TGbotPHP` è una libreria modulare orientata agli oggetti per l'interazione con le API Bot di Telegram.

```text
                           +----------------------+
                           |       TGbotPHP       |
                           |  (Client Principale) |
                           +----------+-----------+
                                      |
         +----------------------------+---------------------------+
         v                            v                           v
+------------------+         +------------------+        +------------------+
| HttpClientTrait  |         | WebhookValidator |        | LogRedactor      |
| (cURL sincrono   |         | (HMAC & Secret   |        | (Sanitizzazione  |
|  con timeout)    |         |  Token check)    |        |  token nei log)  |
+------------------+         +------------------+        +------------------+
         |
         v
+------------------+
|   Telegram API   |
| (HTTPS Endpoint) |
+------------------+
```

| Componente | File/Namespace | Ruolo |
| --- | --- | --- |
| Client | `src/TGbotPHP.php` | Metodi di alto livello (`sendMessage`, `sendPhoto`, `answerCallbackQuery`). |
| HTTP Engine | `src/HttpClientTrait.php` | Gestione trasporto HTTP/cURL con fail-safe e gestione timeout. |
| Webhook | `src/WebhookValidator.php` | Convalida crittografica delle richieste in entrata e blocco accessi non autorizzati. |
| Sicurezza Log | `src/LogRedactor.php` | Mascheramento automatico di bot token, chiavi e numeri di telefono nei log. |
| Test Suite | `tests/` | Suite esaustiva con 100% di copertura e mock HTTP hermetici. |
