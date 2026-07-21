// UMKC VSA gateway bot — the PC-hosted half of the hybrid setup.
// Handles things that need a live connection: welcome messages, presence,
// and moderation follow-up (warning counts + mod-channel logs).
//
// Slash commands are NOT handled here; they go to the Supabase edge function
// via the Interactions Endpoint URL set in the Discord developer portal.
//
// Moderation design: Discord AutoMod (server-side, always on) blocks hate
// speech using Discord's maintained "Slurs" keyword preset. This bot listens
// for those AutoMod executions to DM the offender, track warnings in
// Supabase, log details to the mod channel, and time out repeat offenders.
// If this bot is offline, blocking still happens — only the warning
// bookkeeping pauses.
import {
  ActivityType,
  AutoModerationActionType,
  AutoModerationRuleEventType,
  AutoModerationRuleKeywordPresetType,
  AutoModerationRuleTriggerType,
  Client,
  EmbedBuilder,
  Events,
  GatewayIntentBits,
} from "discord.js";
import { createClient } from "@supabase/supabase-js";

const {
  DISCORD_TOKEN,
  WELCOME_CHANNEL_ID,
  MOD_LOG_CHANNEL_ID,
  SUPABASE_URL,
  SUPABASE_SERVICE_ROLE_KEY,
} = process.env;

const MAX_WARNINGS = 3;
const TIMEOUT_MS = 24 * 60 * 60 * 1000; // 24h timeout on the 3rd warning
const AUTOMOD_RULE_NAME = "UMKC VSA Bot - hate speech";

if (!DISCORD_TOKEN) {
  console.error("Missing DISCORD_TOKEN — copy .env.example to .env and fill it in.");
  process.exit(1);
}

const supabase =
  SUPABASE_URL && SUPABASE_SERVICE_ROLE_KEY
    ? createClient(SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY)
    : null;

const client = new Client({
  intents: [
    GatewayIntentBits.Guilds,
    GatewayIntentBits.GuildMembers, // privileged: "Server Members Intent" in the dev portal
    GatewayIntentBits.MessageContent, // privileged: "Message Content Intent" — needed to log blocked message text
    GatewayIntentBits.AutoModerationConfiguration,
    GatewayIntentBits.AutoModerationExecution,
  ],
});

async function ensureAutoModRule(guild) {
  try {
    const rules = await guild.autoModerationRules.fetch();
    if (rules.some((r) => r.name === AUTOMOD_RULE_NAME)) return;
    await guild.autoModerationRules.create({
      name: AUTOMOD_RULE_NAME,
      eventType: AutoModerationRuleEventType.MessageSend,
      triggerType: AutoModerationRuleTriggerType.KeywordPreset,
      triggerMetadata: {
        presets: [AutoModerationRuleKeywordPresetType.Slurs],
      },
      actions: [
        {
          type: AutoModerationActionType.BlockMessage,
          metadata: {
            customMessage:
              "Hate speech is not tolerated in the UMKC VSA server. This message was blocked and officers have been notified.",
          },
        },
      ],
      enabled: true,
      reason: "UMKC VSA moderation: block slurs/hate speech",
    });
    console.log(`Created AutoMod rule "${AUTOMOD_RULE_NAME}" in ${guild.name}`);
  } catch (err) {
    console.error(
      'Could not create the AutoMod rule (bot needs the "Manage Server" permission):',
      err.message,
    );
  }
}

async function bumpWarnings(userId) {
  if (!supabase) return null;
  try {
    const { data } = await supabase
      .from("discord_warnings")
      .select("count")
      .eq("discord_user_id", userId)
      .maybeSingle();
    const count = (data?.count ?? 0) + 1;
    const { error } = await supabase.from("discord_warnings").upsert({
      discord_user_id: userId,
      count,
      last_offense_at: new Date().toISOString(),
    });
    if (error) throw error;
    return count;
  } catch (err) {
    console.error("Failed to update warning count:", err.message);
    return null;
  }
}

client.once(Events.ClientReady, async (c) => {
  console.log(`Logged in as ${c.user.tag}`);
  c.user.setPresence({
    activities: [
      { name: "custom", type: ActivityType.Custom, state: "✨🍜 𝓱𝓪𝓶 𝓬𝓱𝓸𝓲 🌸✨" },
    ],
  });
  for (const guild of c.guilds.cache.values()) {
    await ensureAutoModRule(guild);
  }
});

client.on(Events.GuildMemberAdd, async (member) => {
  if (!WELCOME_CHANNEL_ID) return;
  const channel = member.guild.channels.cache.get(WELCOME_CHANNEL_ID);
  if (!channel?.isTextBased()) return;
  try {
    await channel.send(
      `Chào mừng ${member}! 🌸 Welcome to the UMKC VSA server — ` +
        `introduce yourself and check out the upcoming events with /events!`,
    );
  } catch (err) {
    console.error("Failed to send welcome message:", err);
  }
});

client.on(Events.AutoModerationActionExecution, async (execution) => {
  if (execution.action.type !== AutoModerationActionType.BlockMessage) return;

  const { guild, userId } = execution;
  const count = await bumpWarnings(userId);
  const remaining = count === null ? null : Math.max(0, MAX_WARNINGS - count);
  const member = await guild.members.fetch(userId).catch(() => null);

  // Warn the offender by DM
  if (member && count !== null) {
    const warningLine =
      count >= MAX_WARNINGS
        ? `This is warning **${count}/${MAX_WARNINGS}** — you have no warnings left and have been timed out for 24 hours.`
        : `This is warning **${count}/${MAX_WARNINGS}** — you have **${remaining}** warning${remaining === 1 ? "" : "s"} left.`;
    await member
      .send(
        `⚠️ Your message in the UMKC VSA server was removed for hate speech.\n${warningLine}\n` +
          `Continued violations will result in removal from the server.`,
      )
      .catch(() => {}); // DMs may be closed; the mod log still records it
  }

  // Time out at max warnings
  let actionTaken = "Message blocked";
  if (member && count !== null && count >= MAX_WARNINGS) {
    try {
      await member.timeout(TIMEOUT_MS, `Reached ${MAX_WARNINGS} hate-speech warnings`);
      actionTaken = "Message blocked · **24h timeout applied**";
    } catch (err) {
      actionTaken = "Message blocked · ⚠️ timeout failed (check bot role/permissions)";
      console.error("Timeout failed:", err.message);
    }
  }

  // Post the offense report to the mod channel
  if (!MOD_LOG_CHANNEL_ID) return;
  const logChannel = guild.channels.cache.get(MOD_LOG_CHANNEL_ID);
  if (!logChannel?.isTextBased()) return;

  const content =
    execution.content ||
    "(message text unavailable — enable the Message Content intent)";
  const warningsField =
    count === null
      ? "Unavailable (database error — check bot logs)"
      : `${count}/${MAX_WARNINGS} (${remaining} remaining)`;

  const embed = new EmbedBuilder()
    .setColor(count !== null && count >= MAX_WARNINGS ? 0xdc2626 : 0xf59e0b)
    .setTitle("🚫 Hate speech blocked")
    .addFields(
      { name: "User", value: `<@${userId}>`, inline: true },
      { name: "User ID", value: userId, inline: true },
      { name: "Channel", value: execution.channelId ? `<#${execution.channelId}>` : "Unknown", inline: true },
      {
        name: "Reason",
        value: `AutoMod rule matched hate-speech keyword${execution.matchedKeyword ? `: \`${execution.matchedKeyword}\`` : ""}`,
      },
      { name: "Exact message", value: content.slice(0, 1024) },
      { name: "Warnings", value: warningsField, inline: true },
      { name: "Action", value: actionTaken, inline: true },
    )
    .setTimestamp(new Date());

  await logChannel
    .send({ embeds: [embed] })
    .catch((err) => console.error("Failed to post mod log:", err.message));
});

client.login(DISCORD_TOKEN);
