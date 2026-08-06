#pragma semicolon 1
#pragma newdecls required

#include <sourcemod>

// REST in Pawn (ripext) est requis et auto-chargé : SourceMod définit déjà
// AUTOLOAD_EXTENSIONS/REQUIRE_EXTENSIONS par défaut, si l'extension est
// absente le plugin refuse de se charger (erreur visible dans sm plugins list).
#include <ripext>

#define PLUGIN_VERSION "1.2.0"

public Plugin myinfo =
{
	name        = "HLFR Match Log Webhook",
	author      = "Highlander France",
	description = "Notifie le site Highlander France quand un match se termine (les logs sont envoyés sur logs.tf par TFTrue)",
	version     = PLUGIN_VERSION,
	url         = "https://highlanderfrance.tf"
};

ConVar g_hEnabled;
ConVar g_hWebhookUrl;
ConVar g_hToken;
ConVar g_hServerName;
ConVar g_hDelay;
ConVar g_hMaxRetries;
ConVar g_hDebug;
ConVar g_hMPTournament;
ConVar g_hHostname;

bool g_InMatch;        // un match de compétition est en cours (mp_tournament actif)
bool g_WebhookPending; // un webhook est déjà en cours (évite les doublons)
int  g_RetriesLeft;    // tentatives restantes
char g_LastMap[PLATFORM_MAX_PATH];

public void OnPluginStart()
{
	CreateConVar("hlfr_match_log_version", PLUGIN_VERSION, "Version du plugin HLFR Match Log Webhook", FCVAR_NOTIFY);

	g_hEnabled    = CreateConVar("hlfr_enable", "1", "Active/désactive le webhook de fin de match.", _, true, 0.0, true, 1.0);
	g_hWebhookUrl = CreateConVar("hlfr_webhook_url", "https://highlanderfrance.tf/api/server/match-ended", "URL du webhook du site Highlander France.");
	g_hToken      = CreateConVar("hlfr_webhook_token", "", "Token partagé (secret) pour authentifier le webhook. Doit correspondre à SERVER_WEBHOOK_TOKEN du site.", FCVAR_PROTECTED);
	g_hServerName = CreateConVar("hlfr_server_name", "", "Nom du serveur de match envoyé au site (vide = hostname).");
	g_hDelay      = CreateConVar("hlfr_delay", "30.0", "Délai (secondes) entre la fin de match et l'envoi du webhook, pour laisser TFTrue uploader le log sur logs.tf.", _, true, 0.0);
	g_hMaxRetries = CreateConVar("hlfr_max_retries", "3", "Nombre de nouvelles tentatives si le webhook échoue.", _, true, 0.0);
	g_hDebug      = CreateConVar("hlfr_debug", "0", "Logs de debug supplémentaires dans la console du serveur.", _, true, 0.0, true, 1.0);

	AutoExecConfig(true, "hlfr_match_log");

	g_hMPTournament = FindConVar("mp_tournament");
	g_hHostname     = FindConVar("hostname");

	HookEvent("teamplay_game_over",  Event_GameOver);
	HookEvent("tf_game_over",        Event_GameOver);
	HookEvent("teamplay_round_win",  Event_RoundWin);
	HookEvent("teamplay_round_start", Event_RoundStart);

	RegAdminCmd("sm_hlfr_sync", Command_Sync, ADMFLAG_GENERIC, "Déclenche manuellement le webhook de fin de match (test).");
}

public void OnMapStart()
{
	// Nouvelle carte = nouveau potentiel match. Le prochain round_start re-arme le plugin.
	g_InMatch = false;
}

public void Event_RoundStart(Event event, const char[] name, bool dontBroadcast)
{
	// Un round démarre alors que le mode tournoi est actif : c'est un match.
	if (g_hMPTournament != null && GetConVarBool(g_hMPTournament))
	{
		g_InMatch = true;
	}
}

public void Event_RoundWin(Event event, const char[] name, bool dontBroadcast)
{
	if (g_hMPTournament != null && GetConVarBool(g_hMPTournament))
	{
		g_InMatch = true;
	}
}

public void Event_GameOver(Event event, const char[] name, bool dontBroadcast)
{
	if (!g_InMatch)
	{
		return; // map qui change / partie publique : on ignore
	}

	g_InMatch = false;

	if (!GetConVarBool(g_hEnabled))
	{
		return;
	}

	if (g_WebhookPending)
	{
		return;
	}

	GetCurrentMap(g_LastMap, sizeof(g_LastMap));
	g_RetriesLeft = GetConVarInt(g_hMaxRetries);
	g_WebhookPending = true;

	float delay = GetConVarFloat(g_hDelay);
	if (GetConVarBool(g_hDebug))
	{
		PrintToServer("[HLFR] Fin de match détectée (map %s). Webhook dans %.0f s.", g_LastMap, delay);
	}

	// TIMER_FLAG_NO_MAPCHANGE : on envoie même si la carte change avant la fin du délai.
	CreateTimer(delay, Timer_FireWebhook, _, TIMER_FLAG_NO_MAPCHANGE);
}

public Action Command_Sync(int client, int args)
{
	if (!GetConVarBool(g_hEnabled))
	{
		ReplyToCommand(client, "[HLFR] Webhook désactivé (hlfr_enable 0).");
		return Plugin_Handled;
	}

	if (g_WebhookPending)
	{
		ReplyToCommand(client, "[HLFR] Un webhook est déjà en cours d'envoi.");
		return Plugin_Handled;
	}

	GetCurrentMap(g_LastMap, sizeof(g_LastMap));
	g_RetriesLeft = GetConVarInt(g_hMaxRetries);
	g_WebhookPending = true;

	CreateTimer(0.1, Timer_FireWebhook, _, TIMER_FLAG_NO_MAPCHANGE);
	ReplyToCommand(client, "[HLFR] Webhook de fin de match déclenché.");
	return Plugin_Handled;
}

public Action Timer_FireWebhook(Handle timer)
{
	SendWebhook();
	return Plugin_Stop;
}

public Action Timer_Retry(Handle timer)
{
	SendWebhook();
	return Plugin_Stop;
}

void SendWebhook()
{
	char url[512], token[256], server[256];
	GetConVarString(g_hWebhookUrl, url, sizeof(url));
	GetConVarString(g_hToken, token, sizeof(token));
	GetConVarString(g_hServerName, server, sizeof(server));

	if (server[0] == '\0' && g_hHostname != null)
	{
		GetConVarString(g_hHostname, server, sizeof(server));
	}

	if (url[0] == '\0' || token[0] == '\0')
	{
		LogError("[HLFR] Webhook non envoyé : hlfr_webhook_url ou hlfr_webhook_token vide.");
		PrintToServer("[HLFR] Webhook non envoyé : hlfr_webhook_url ou hlfr_webhook_token vide.");
		g_WebhookPending = false;
		return;
	}

	if (GetConVarBool(g_hDebug))
	{
		PrintToServer("[HLFR] Envoi du webhook vers %s (server=%s, map=%s).", url, server, g_LastMap);
	}

	HTTPRequest request = new HTTPRequest(url);
	request.SetHeader("User-Agent", "hlfr_match_log");

	JSONObject body = new JSONObject();
	body.SetString("token", token);
	body.SetString("server", server);
	body.SetString("map", g_LastMap);
	body.SetString("source", "hlfr_match_log");

	// Post prend possession de `body` : ne pas faire delete.
	request.Post(body, Callback_Webhook);
}

void Callback_Webhook(HTTPResponse response, int value)
{
	int status = view_as<int>(response.Status);

	if (status >= 200 && status <= 299)
	{
		int processed = -1;
		char contentType[64];
		if (response.GetHeader("Content-Type", contentType, sizeof(contentType))
			&& StrContains(contentType, "json") != -1)
		{
			JSON data = response.Data;
			if (data != null)
			{
				JSONObject obj = view_as<JSONObject>(data);
				if (obj.HasKey("processed_logs"))
				{
					processed = obj.GetInt("processed_logs");
				}
			}
		}

		if (processed == 1)
		{
			PrintToServer("[HLFR] Webhook de fin de match accepté (HTTP %d) - 1 nouveau log traité.", status);
			LogMessage("Webhook fin de match envoyé avec succès (HTTP %d) - 1 nouveau log traité.", status);
		}
		else if (processed >= 0)
		{
			PrintToServer("[HLFR] Webhook de fin de match accepté (HTTP %d) - %d nouveaux logs traités.", status, processed);
			LogMessage("Webhook fin de match envoyé avec succès (HTTP %d) - %d nouveaux logs traités.", status, processed);
		}
		else
		{
			PrintToServer("[HLFR] Webhook de fin de match accepté (HTTP %d).", status);
			LogMessage("Webhook fin de match envoyé avec succès (HTTP %d).", status);
		}

		g_WebhookPending = false;
		return;
	}

	// Hints de diagnostic pour les codes fréquents.
	if (status == 0)
	{
		PrintToServer("[HLFR] Webhook impossible : serveur injoignable ou erreur TLS (HTTP 0). Vérifiez que le site est accessible depuis ce serveur.");
	}
	else if (status == 403)
	{
		PrintToServer("[HLFR] Webhook refusé (HTTP 403) : token incorrect ou IP non autorisée.");
	}
	else if (status == 404)
	{
		PrintToServer("[HLFR] Webhook refusé (HTTP 404) : mauvaise URL hlfr_webhook_url.");
	}
	else if (status >= 500)
	{
		PrintToServer("[HLFR] Webhook refusé (HTTP %d) : erreur côté site, sera réessayé.", status);
	}
	else
	{
		PrintToServer("[HLFR] Webhook refusé (HTTP %d).", status);
	}

	g_RetriesLeft--;

	if (g_RetriesLeft > 0)
	{
		float backoff = GetConVarFloat(g_hDelay) + float(GetConVarInt(g_hMaxRetries) - g_RetriesLeft) * 30.0;
		PrintToServer("[HLFR] Nouvel essai dans %.0f s (%d restant%s).", backoff, g_RetriesLeft, g_RetriesLeft > 1 ? "s" : "");
		CreateTimer(backoff, Timer_Retry, _, TIMER_FLAG_NO_MAPCHANGE);
		return;
	}

	LogError("[HLFR] Webhook de fin de match définitivement refusé (HTTP %d).", status);
	PrintToServer("[HLFR] Webhook de fin de match définitivement refusé (HTTP %d).", status);
	g_WebhookPending = false;
}
