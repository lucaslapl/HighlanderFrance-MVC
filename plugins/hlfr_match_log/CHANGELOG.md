# Changelog — HLFR Match Log Webhook

## 1.2.0 — 2026-08-06

- Le site renvoie désormais le **nombre de nouveaux logs traités** dans la
  réponse du webhook (`processed_logs`). Le plugin l'affiche en console :
  `[HLFR] Webhook de fin de match accepté (HTTP 200) - 1 nouveau log traité.`
  (ou `N nouveaux logs traités`). 0 = le log du match n'a pas encore été trouvé
  sur logs.tf (titre ne correspondant pas, ou upload TFTrue plus lent).

## 1.1.0 — 2026-08-06

- Ajout de messages de diagnostic dans la console serveur selon le code HTTP :
  - `HTTP 0` → serveur injoignable ou erreur TLS
  - `HTTP 403` → token incorrect ou IP non autorisée
  - `HTTP 404` → mauvaise URL `hlfr_webhook_url`
  - `HTTP 5xx` → erreur côté site (revoir `_scripts/cron_debug.log`)
- Message d'erreur visible en console si `hlfr_webhook_url` / `hlfr_webhook_token`
  sont vides (au lieu d'un simple `LogError`).

## 1.0.0 — 2026-08-06

- Version initiale.
- Détection de fin de match via les événements `tf_game_over` /
  `teamplay_game_over`, uniquement si un match est en cours (`mp_tournament`).
- Délai configurable (`hlfr_delay`) avant l'envoi pour laisser TFTrue uploader
  le log sur logs.tf.
- Envoi d'un webhook `POST` JSON à l'endpoint du site : `{ token, server, map }`.
- Nouvelles tentatives automatiques jusqu'à `hlfr_max_retries` (backoff
  progressif), timers insensibles au changement de carte.
- Anti-doublon (`g_WebhookPending`) et commande admin `sm_hlfr_sync` pour tester.
- Dépendance : extension **REST in Pawn (sm-ripext)**, SourceMod 1.10+.
