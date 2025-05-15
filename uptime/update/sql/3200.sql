UPDATE `settings` SET `value` = '{\"version\":\"32.0.0\", \"code\":\"3200\"}' WHERE `key` = 'product_info';

-- SEPARATOR --

INSERT IGNORE INTO `settings` (`key`, `value`) VALUES ('notification_handlers', '{"twilio_sid":"","twilio_token":"","twilio_number":"","whatsapp_number_id":"","whatsapp_access_token":"","email_is_enabled":true,"webhook_is_enabled":true,"slack_is_enabled":true,"discord_is_enabled":true,"telegram_is_enabled":true,"microsoft_teams_is_enabled":true,"twilio_is_enabled":false,"twilio_call_is_enabled":false,"whatsapp_is_enabled":false}');