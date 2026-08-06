# HLFR Match Log Webhook

Plugin SourceMod qui notifie le site Highlander France quand un **match se
termine** sur un serveur de match TF2 (TFTrue), afin que le site mette à jour
immédiatement les stats joueurs et le leaderboard — sans attendre le CRON.

## Principe

1. TF2 émet `teamplay_game_over` / `tf_game_over` quand les conditions de
   victoire sont atteintes (= match terminé). Le plugin ne réagit que si un
   match est en cours (convar `mp_tournament` actif), donc pas de faux
   déclenchement en partie publique ou lors d'un simple changement de carte.
2. Après un délai configurable (30 s par défaut), le plugin envoie un **webhook
   POST JSON** à l'endpoint du site. Ce délai laisse TFTrue terminer l'upload
   du log sur logs.tf.
3. Le site authentifie la requête (token partagé), puis exécute la chaîne de
   mise à jour des stats et du leaderboard.

## Prérequis

- Serveur **SourceMod 1.10+**.
- Extension **REST in Pawn (sm-ripext)** : <https://github.com/ErikMinekus/sm-ripext/releases>
  (à déposer dans `addons/sourcemod/extensions/`).
- **TFTrue** doit déjà uploader les logs sur logs.tf :
  `tftrue_logs_apikey "<cle logs.tf>"` (et éventuellement
  `tftrue_logs_name_prefix "Highlander France"` pour que le site retrouve le
  log via ses requêtes par titre).

## Installation

Sur chaque serveur de match :

```
addons/sourcemod/scripting/hlfr_match_log.sp   ← source (compilation)
addons/sourcemod/plugins/hlfr_match_log.smx    ← binaire (ou .sp compilé)
cfg/sourcemod/hlfr_match_log.cfg               ← configuration
```

1. Copier `hlfr_match_log.cfg` dans `cfg/sourcemod/`.
2. Renseigner les CVars :
   - `hlfr_webhook_url` → URL du site (endpoint webhook).
   - `hlfr_webhook_token` → secret partagé, identique à
     `SERVER_WEBHOOK_TOKEN` dans `config/.env` du site.
   - `hlfr_server_name` → identifiant du serveur (pour les logs du site).
3. Placer le `.smx` dans `addons/sourcemod/plugins/`. Un binaire précompilé
   (`hlfr_match_log.smx`, SourceMod 1.12) est fourni ; pour recompiler :
   `spcomp hlfr_match_log.sp`.
4. Recharger : `sm plugins reload hlfr_match_log` (ou restart).

## CVars

| Convar | Défaut | Description |
|---|---|---|
| `hlfr_enable` | `1` | Active/désactive le webhook |
| `hlfr_webhook_url` | — | URL de l'endpoint webhook du site |
| `hlfr_webhook_token` | — | Token partagé (secret, `FCVAR_PROTECTED`) |
| `hlfr_server_name` | vide | Nom du serveur envoyé au site (vide = `hostname`) |
| `hlfr_delay` | `30.0` | Délai (s) après la fin du match avant l'envoi |
| `hlfr_max_retries` | `3` | Nouvelles tentatives si le webhook échoue |
| `hlfr_debug` | `0` | Logs de debug dans la console |

## Tests

- `sm exts list` : vérifier que **REST in Pawn** est chargé (le plugin refuse de
  se charger sinon).
- `sm_hlfr_sync` (admin) : déclenche un webhook **immédiatement**, sans attendre
  la fin d'un match (la détection automatique n'est pas nécessaire pour tester).
- Messages dans la console serveur :
  - `[HLFR] Webhook de fin de match accepté (HTTP 200) - 1 nouveau log traité.`
    → tout fonctionne ; le log du match a bien été trouvé et traité par le site
    (le nombre vient de la réponse du site).
  - `[HLFR] Webhook de fin de match accepté (HTTP 200) - 0 nouveaux logs traités.`
    → webhook OK mais le log n'a pas encore été trouvé sur logs.tf : le titre du
    log ne contient pas « Highlander France » / « highlanderfrance.tf »
    (vérifier `tftrue_logs_name_prefix`), ou l'upload TFTrue est en retard.
  - `[HLFR] Webhook impossible : serveur injoignable ou erreur TLS (HTTP 0).`
    → le site est inaccessible depuis le serveur de jeu (URL, DNS, firewall).
  - `[HLFR] Webhook refusé (HTTP 403) : token incorrect ou IP non autorisée.`
  - `[HLFR] Webhook refusé (HTTP 404) : mauvaise URL hlfr_webhook_url.`
  - `[HLFR] Webhook refusé (HTTP 500...)` → erreur côté site (voir
    `_scripts/cron_debug.log`).

## Robustesse

- **Race condition logs.tf** : `hlfr_delay` couvre le temps d'upload de TFTrue ;
  en cas d'échec HTTP le plugin réessaie jusqu'à `hlfr_max_retries` fois.
- **Doublons** : un seul webhook pend à la fois (`g_WebhookPending`), et le
  plugin se désarme dès le premier `game_over`.
- **Changement de carte** : les timers utilisent `TIMER_FLAG_NO_MAPCHANGE`, le
  webhook part même si la carte change avant la fin du délai.
