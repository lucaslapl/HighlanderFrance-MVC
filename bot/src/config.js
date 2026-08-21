import dotenv from 'dotenv';

dotenv.config();

function intOr(name, fallback) {
    const parsed = Number.parseInt(process.env[name] ?? '', 10);

    return Number.isFinite(parsed) ? parsed : fallback;
}

export const config = {
    discordToken: process.env.DISCORD_TOKEN ?? '',
    guildId: process.env.GUILD_ID ?? '',
    siteWebhookUrl: process.env.SITE_WEBHOOK_URL ?? '',
    siteWebhookToken: process.env.SITE_WEBHOOK_TOKEN ?? '',
    port: intOr('PORT', 3000),
    syncIntervalMinutes: intOr('SYNC_INTERVAL_MINUTES', 360),
    minPushIntervalMs: intOr('MIN_PUSH_INTERVAL_SECONDS', 60) * 1000,
};

/**
 * Variables manquantes : ne bloque pas le démarrage (Passenger afficherait
 * une erreur générique illisible) mais dégrade l'app, qui le signale
 * clairement via /health.
 */
export const configErrors = Object.entries({
    DISCORD_TOKEN: config.discordToken,
    GUILD_ID: config.guildId,
    SITE_WEBHOOK_URL: config.siteWebhookUrl,
    SITE_WEBHOOK_TOKEN: config.siteWebhookToken,
})
    .filter(([, value]) => value === '')
    .map(([name]) => `Variable d'environnement manquante : ${name}`);
