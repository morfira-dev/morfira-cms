import telegram from '@nuykon/semantic-release-tg-bot';

function isConfigured(env) {
  return Boolean(env.TELEGRAM_BOT_TOKEN?.trim() && env.TELEGRAM_CHAT_ID?.trim());
}

export async function verifyConditions(options, context) {
  if (!isConfigured(context.env)) {
    context.logger.log('Skipping Telegram notifications. Set TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID to enable them.');
    return;
  }

  await telegram.verifyConditions(options, context);
}

export async function success(options, context) {
  if (isConfigured(context.env)) {
    await telegram.success(options, context);
  }
}
