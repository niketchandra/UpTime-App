<?php
/*
 * Copyright (c) 2025 AltumCode (https://altumcode.com/)
 *
 * This software is licensed exclusively by AltumCode and is sold only via https://altumcode.com/.
 * Unauthorized distribution, modification, or use of this software without a valid license is not permitted and may be subject to applicable legal actions.
 *
 * 🌍 View all other existing AltumCode projects via https://altumcode.com/
 * 📧 Get in touch for support or general queries via https://altumcode.com/contact
 * 📤 Download the latest version via https://altumcode.com/downloads
 *
 * 🐦 X/Twitter: https://x.com/AltumCode
 * 📘 Facebook: https://facebook.com/altumcode
 * 📸 Instagram: https://instagram.com/altumcode
 */

namespace Altum\Controllers;

use Altum\Logger;
use Altum\Models\User;

defined('ALTUMCODE') || die();

class Cron extends Controller {

    public function index() {
        die();
    }

    private function initiate() {
        /* Initiation */
        set_time_limit(0);

        /* Make sure the key is correct */
        if(!isset($_GET['key']) || (isset($_GET['key']) && $_GET['key'] != settings()->cron->key)) {
            die();
        }

        /* Send webhook notification if needed */
        if(settings()->webhooks->cron_start) {
            $backtrace = debug_backtrace();
            fire_and_forget('post', settings()->webhooks->cron_start, [
                'type' => $backtrace[1]['function'] ?? null,
                'datetime' => get_date(),
            ]);
        }
    }

    private function close() {
        /* Send webhook notification if needed */
        if(settings()->webhooks->cron_end) {
            $backtrace = debug_backtrace();
            fire_and_forget('post', settings()->webhooks->cron_end, [
                'type' => $backtrace[1]['function'] ?? null,
                'datetime' => get_date(),
            ]);
        }
    }

    private function update_cron_execution_datetimes($key) {
        $date = get_date();

        /* Database query */
        database()->query("UPDATE `settings` SET `value` = JSON_SET(`value`, '$.{$key}', '{$date}') WHERE `key` = 'cron'");
    }

    public function reset() {

        $this->initiate();

        $this->users_plan_expiry_checker();

        $this->users_deletion_reminder();

        $this->auto_delete_inactive_users();

        $this->auto_delete_unconfirmed_users();

        $this->users_plan_expiry_reminder();

        $this->update_cron_execution_datetimes('reset_datetime');

        /* Make sure the reset date month is different than the current one to avoid double resetting */
        $reset_date = settings()->cron->reset_date ? (new \DateTime(settings()->cron->reset_date))->format('m') : null;
        $current_date = (new \DateTime())->format('m');

        if($reset_date != $current_date) {
            $this->logs_cleanup();

            $this->users_logs_cleanup();

            $this->internal_notifications_cleanup();

            $this->statistics_cleanup();

            $this->update_cron_execution_datetimes('reset_date');

            /* Clear the cache */
            cache()->deleteItem('settings');
        }

        $this->close();
    }

    private function users_plan_expiry_checker() {
        if(!settings()->payment->user_plan_expiry_checker_is_enabled) {
            return;
        }

        $date = get_date();

        $result = database()->query("
            SELECT `user_id`
            FROM `users`
            WHERE 
                `plan_id` <> 'free'
				AND `plan_expiration_date` < '{$date}' 
            LIMIT 25
        ");

        /* Go through each result */
        while($user = $result->fetch_object()) {

            /* Switch the user to the default plan */
            db()->where('user_id', $user->user_id)->update('users', [
                'plan_id' => 'free',
                'plan_settings' => json_encode(settings()->plan_free->settings),
                'payment_subscription_id' => ''
            ]);

            /* Clear the cache */
            cache()->deleteItemsByTag('user_id=' .  \Altum\Authentication::$user_id);

            if(DEBUG) {
                echo sprintf('users_plan_expiry_checker() -> Plan expired for user_id %s - reverting account to free plan', $user->user_id);
            }
        }
    }

    private function users_deletion_reminder() {
        if(!settings()->users->auto_delete_inactive_users) {
            return;
        }

        /* Determine when to send the email reminder */
        $days_until_deletion = settings()->users->user_deletion_reminder;
        $days = settings()->users->auto_delete_inactive_users - $days_until_deletion;
        $past_date = (new \DateTime())->modify('-' . $days . ' days')->format('Y-m-d H:i:s');

        /* Get the users that need to be reminded */
        $result = database()->query("
            SELECT `user_id`, `name`, `email`, `language`, `anti_phishing_code` 
            FROM `users` 
            WHERE 
                `plan_id` = 'free' 
                AND `last_activity` < '{$past_date}' 
                AND `user_deletion_reminder` = 0 
                AND `type` = 0 
            LIMIT 25
        ");

        /* Go through each result */
        while($user = $result->fetch_object()) {

            /* Prepare the email */
            $email_template = get_email_template(
                [
                    '{{DAYS_UNTIL_DELETION}}' => $days_until_deletion,
                ],
                l('global.emails.user_deletion_reminder.subject', $user->language),
                [
                    '{{DAYS_UNTIL_DELETION}}' => $days_until_deletion,
                    '{{LOGIN_LINK}}' => url('login'),
                    '{{NAME}}' => $user->name,
                ],
                l('global.emails.user_deletion_reminder.body', $user->language)
            );

            if(settings()->users->user_deletion_reminder) {
                send_mail($user->email, $email_template->subject, $email_template->body, ['anti_phishing_code' => $user->anti_phishing_code, 'language' => $user->language]);
            }

            /* Update user */
            db()->where('user_id', $user->user_id)->update('users', ['user_deletion_reminder' => 1]);

            if(DEBUG) {
                if(settings()->users->user_deletion_reminder) echo sprintf('users_deletion_reminder() -> User deletion reminder email sent for user_id %s', $user->user_id);
            }
        }

    }

    private function auto_delete_inactive_users() {
        if(!settings()->users->auto_delete_inactive_users) {
            return;
        }

        /* Determine what users to delete */
        $days = settings()->users->auto_delete_inactive_users;
        $past_date = (new \DateTime())->modify('-' . $days . ' days')->format('Y-m-d H:i:s');

        /* Get the users that need to be reminded */
        $result = database()->query("
            SELECT `user_id`, `name`, `email`, `language`, `anti_phishing_code` FROM `users` WHERE `plan_id` = 'free' AND `last_activity` < '{$past_date}' AND `user_deletion_reminder` = 1 AND `type` = 0 LIMIT 25
        ");

        /* Go through each result */
        while($user = $result->fetch_object()) {

            /* Prepare the email */
            $email_template = get_email_template(
                [],
                l('global.emails.auto_delete_inactive_users.subject', $user->language),
                [
                    '{{INACTIVITY_DAYS}}' => settings()->users->auto_delete_inactive_users,
                    '{{REGISTER_LINK}}' => url('register'),
                    '{{NAME}}' => $user->name,
                ],
                l('global.emails.auto_delete_inactive_users.body', $user->language)
            );

            send_mail($user->email, $email_template->subject, $email_template->body, ['anti_phishing_code' => $user->anti_phishing_code, 'language' => $user->language]);

            /* Delete user */
            (new User())->delete($user->user_id);

            if(DEBUG) {
                echo sprintf('User deletion for inactivity user_id %s', $user->user_id);
            }
        }

    }

    private function auto_delete_unconfirmed_users() {
        if(!settings()->users->auto_delete_unconfirmed_users) {
            return;
        }

        /* Determine what users to delete */
        $days = settings()->users->auto_delete_unconfirmed_users;
        $past_date = (new \DateTime())->modify('-' . $days . ' days')->format('Y-m-d H:i:s');

        /* Get the users that need to be reminded */
        $result = database()->query("SELECT `user_id` FROM `users` WHERE `status` = '0' AND `datetime` < '{$past_date}' LIMIT 100");

        /* Go through each result */
        while($user = $result->fetch_object()) {

            /* Delete user */
            (new User())->delete($user->user_id);

            if(DEBUG) {
                echo sprintf('User deleted for unconfirmed account user_id %s', $user->user_id);
            }
        }
    }

    private function logs_cleanup() {
        /* Clear files caches */
        clearstatcache();

        $current_month = (new \DateTime())->format('m');

        $deleted_count = 0;

        /* Get the data */
        foreach(glob(UPLOADS_PATH . 'logs/' . '*.log') as $file_path) {
            $file_last_modified = filemtime($file_path);

            if((new \DateTime())->setTimestamp($file_last_modified)->format('m') != $current_month) {
                unlink($file_path);
                $deleted_count++;
            }
        }

        if(DEBUG) {
            echo sprintf('logs_cleanup: Deleted %s file logs.', $deleted_count);
        }
    }

    private function users_logs_cleanup() {
        /* Delete old users logs */
        $ninety_days_ago_datetime = (new \DateTime())->modify('-90 days')->format('Y-m-d H:i:s');
        db()->where('datetime', $ninety_days_ago_datetime, '<')->delete('users_logs');
    }

    private function internal_notifications_cleanup() {
        /* Delete old users notifications */
        $ninety_days_ago_datetime = (new \DateTime())->modify('-30 days')->format('Y-m-d H:i:s');
        db()->where('datetime', $ninety_days_ago_datetime, '<')->delete('internal_notifications');
    }

    private function statistics_cleanup() {

        /* Only clean users that have not been cleaned for 1 day */
        $now_datetime = get_date();

        /* Clean the track notifications table based on the users plan */
        $result = database()->query("SELECT `user_id`, `plan_settings` FROM `users` WHERE `status` = 1 AND `next_cleanup_datetime` < '{$now_datetime}'");

        /* Go through each result */
        while($user = $result->fetch_object()) {
            /* Update user cleanup date */
            db()->where('user_id', $user->user_id)->update('users', ['next_cleanup_datetime' => (new \DateTime())->modify('+1 days')->format('Y-m-d H:i:s')]);

            $user->settings = json_decode($user->settings ?? '');

            if($user->plan_settings->statistics_retention == -1) continue;

            /* Clear out old notification statistics logs */
            $x_days_ago_datetime = (new \DateTime())->modify('-' . ($user->plan_settings->statistics_retention ?? 90) . ' days')->format('Y-m-d H:i:s');
            database()->query("DELETE FROM `statistics` WHERE `user_id` = {$user->user_id} AND `datetime` < '{$x_days_ago_datetime}'");

            if(DEBUG) {
                echo sprintf('Status pages statistics cleanup done for user_id %s', $user->user_id);
            }
        }

    }

    private function users_plan_expiry_reminder() {
        if(!settings()->payment->user_plan_expiry_reminder) {
            return;
        }

        /* Determine when to send the email reminder */
        $days = settings()->payment->user_plan_expiry_reminder;
        $future_date = (new \DateTime())->modify('+' . $days . ' days')->format('Y-m-d H:i:s');

        /* Get potential monitors from users that have almost all the conditions to get an email report right now */
        $result = database()->query("
            SELECT
                `user_id`,
                `name`,
                `email`,
                `plan_id`,
                `plan_expiration_date`,
                `language`,
                `anti_phishing_code`
            FROM 
                `users`
            WHERE 
                `status` = 1
                AND `plan_id` <> 'free'
                AND `plan_expiry_reminder` = '0'
                AND (`payment_subscription_id` IS NULL OR `payment_subscription_id` = '')
				AND `plan_expiration_date` < '{$future_date}'
            LIMIT 25
        ");

        $plans = [];
        if($result->num_rows) {
            $plans = (new \Altum\Models\Plan())->get_plans();
        }

        /* Go through each result */
        while($user = $result->fetch_object()) {

            /* Determine the exact days until expiration */
            $days_until_expiration = (new \DateTime($user->plan_expiration_date))->diff((new \DateTime()))->days;

            /* Prepare the email */
            $email_template = get_email_template(
                [
                    '{{DAYS_UNTIL_EXPIRATION}}' => $days_until_expiration,
                ],
                l('global.emails.user_plan_expiry_reminder.subject', $user->language),
                [
                    '{{DAYS_UNTIL_EXPIRATION}}' => $days_until_expiration,
                    '{{USER_PLAN_RENEW_LINK}}' => url('pay/' . $user->plan_id),
                    '{{NAME}}' => $user->name,
                    '{{PLAN_NAME}}' => $plans[$user->plan_id]->name,
                ],
                l('global.emails.user_plan_expiry_reminder.body', $user->language)
            );

            send_mail($user->email, $email_template->subject, $email_template->body, ['anti_phishing_code' => $user->anti_phishing_code, 'language' => $user->language]);

            /* Update user */
            db()->where('user_id', $user->user_id)->update('users', ['plan_expiry_reminder' => 1]);

            if(DEBUG) {
                echo sprintf('users_plan_expiry_reminder() -> Email sent for user_id %s', $user->user_id);
            }
        }

    }

    public function monitors() {

        if(!settings()->monitors_heartbeats->monitors_is_enabled) {
            return;
        }

        $this->initiate();

        $date = get_date();

        /* Update cron job last run date */
        $this->update_cron_execution_datetimes('monitors_datetime');

        /* Get available ping servers */
        $ping_servers = (new \Altum\Models\PingServers())->get_ping_servers();

        /* Determine how many checks to do */
        $foreach_loops = php_sapi_name() == 'cli' ? 50 : 35;
        $checks_limit = php_sapi_name() == 'cli' ? 5 : 5;
        $query_limit = $checks_limit * 3;

        for($i = 1; $i <= $foreach_loops; $i++) {
            $result = database()->query("
                SELECT
                    `monitors`.*,
                    `users`.`email`,
                    `users`.`plan_settings`,
                    `users`.`language`,
                    `users`.`timezone`,
                    `users`.`anti_phishing_code`
                FROM 
                    `monitors`
                LEFT JOIN 
                    `users` ON `monitors`.`user_id` = `users`.`user_id` 
                WHERE 
                    `monitors`.`is_enabled` = 1
                    AND `monitors`.`next_check_datetime` <= '{$date}' 
                    AND `users`.`status` = 1
                ORDER BY `monitors`.`next_check_datetime`
                LIMIT {$query_limit}
            ");

            /* Break if no results */
            if(!$result->num_rows) break;

            $callables = [];

            while($row = $result->fetch_object()) {
                $row->plan_settings = json_decode($row->plan_settings);
                $row->settings = json_decode($row->settings ?? '');
                $row->ping_servers_ids = json_decode($row->ping_servers_ids);
                $row->notifications = json_decode($row->notifications ?? '');
                $row->last_logs = json_decode($row->last_logs ?? '');

                $callables[$row->monitor_id] = function () use ($row, $ping_servers)  {
                    /* Get available notification handlers */
                    $notification_handlers = (new \Altum\Models\NotificationHandlers())->get_notification_handlers_by_user_id($row->user_id);

                    if(DEBUG) printf("Starting to check %s (%s) monitor...\n", $row->name, $row->target);

                    echo $row->target . '<br />';
                    $check = \Altum\Helpers\Monitor::check($row, $ping_servers);
                    echo $row->target . '<br />';

                    /* If the monitor is down, double check to be sure */
                    if(!$check['is_ok'] && settings()->monitors_heartbeats->monitors_double_check_is_enabled) {
                        sleep(settings()->monitors_heartbeats->monitors_double_check_wait ?? 3);
                        $check = \Altum\Helpers\Monitor::check($row, $ping_servers, $check['ping_server_id']);
                    }

                    $vars = \Altum\Helpers\Monitor::vars($row, $check);

                    \Unirest\Request::clearCurlOpts();

                    /* Insert the history log */
                    $monitor_log_id = db()->insert('monitors_logs', [
                        'monitor_id' => $row->monitor_id,
                        'ping_server_id' => $check['ping_server_id'],
                        'user_id' => $row->user_id,
                        'is_ok' => $check['is_ok'],
                        'response_time' => $check['response_time'],
                        'response_status_code' => $check['response_status_code'],
                        'response_body' => $check['response_body'],
                        'error' => isset($check['error']) ? json_encode($check['error']) : null,
                        'datetime' => get_date()
                    ]);

                    /* Create / update an incident if needed */
                    $incident_id = $row->incident_id;

                    if(!$check['is_ok'] && !$row->incident_id) {

                        /* Get the language for the user and set the timezone */
                        \Altum\Date::$timezone = $row->timezone;

                        /* Database query */
                        $incident_id = db()->insert('incidents', [
                            'user_id' => $row->user_id,
                            'monitor_id' => $row->monitor_id,
                            'start_monitor_log_id' => $monitor_log_id,
                            'start_datetime' => get_date()
                        ]);

                        /* Processing the notification handlers */
                        foreach($notification_handlers as $notification_handler) {
                            if(!$notification_handler->is_enabled) continue;
                            if(!in_array($notification_handler->notification_handler_id, $row->notifications->is_ok)) continue;

                            switch($notification_handler->type) {
                                case 'email':

                                    /* Prepare the email title */
                                    $email_title = sprintf(l('cron.is_not_ok.title', $row->language), $row->name);

                                    /* Prepare the View for the email content */
                                    $data = [
                                        'row' => $row,
                                        'error' => isset($check['error']) ? (array) $check['error'] : null,
                                    ];

                                    $email_content = (new \Altum\View('partials/cron/monitor_is_not_ok', (array) $this))->run($data);

                                    /* Send the email */
                                    send_mail($notification_handler->settings->email, $email_title, $email_content, ['anti_phishing_code' => $row->anti_phishing_code, 'language' => $row->language]);

                                    break;

                                case 'webhook':

                                    fire_and_forget('post', $notification_handler->settings->webhook, [
                                        'monitor_id' => $row->monitor_id,
                                        'name' => $row->name,
                                        'is_ok' => $check['is_ok'],
                                        'url' => url('monitor/' . $row->monitor_id),
                                    ]);

                                    break;

                                case 'slack':

                                    try {
                                        \Unirest\Request::post(
                                            $notification_handler->settings->slack,
                                            ['Accept' => 'application/json'],
                                            \Unirest\Request\Body::json([
                                                'text' => sprintf(
                                                    l('monitor.simple_notification.is_not_ok', $row->language),
                                                    $row->name,
                                                    $row->target . ($row->port ? ':' . $row->port : null),
                                                    "\r\n\r\n",
                                                    url('monitor/' . $row->monitor_id)
                                                ),
                                                'username' => settings()->main->title,
                                                'icon_emoji' => ':large_red_square:'
                                            ])
                                        );
                                    } catch (\Exception $exception) {
                                        error_log($exception->getMessage());
                                    }

                                    break;

                                case 'discord':

                                    try {
                                        fire_and_forget(
                                            'POST',
                                            $notification_handler->settings->discord,
                                            [
                                                'embeds' => [
                                                    [
                                                        'title' => sprintf(
                                                            l('monitor.simple_notification.is_not_ok', $row->language),
                                                            $row->name,
                                                            $row->target . ($row->port ? ':' . $row->port : null),
                                                            "\r\n\r\n",
                                                            url('monitor/' . $row->monitor_id)
                                                        ),
                                                        'color' => '14431557',
                                                    ]
                                                ],
                                            ],
                                            'json',
                                            [
                                                'Accept' => 'application/json',
                                                'Content-Type' => 'application/json',
                                            ],
                                        );
                                    } catch (\Exception $exception) {
                                        error_log($exception->getMessage());
                                    }

                                    break;

                                case 'telegram':

                                    try {
                                        fire_and_forget(
                                            'GET',
                                            sprintf(
                                                'https://api.telegram.org/bot%s/sendMessage?chat_id=%s&text=%s',
                                                $notification_handler->settings->telegram,
                                                $notification_handler->settings->telegram_chat_id,
                                                sprintf(
                                                    l('monitor.simple_notification.is_not_ok', $row->language),
                                                    $row->name,
                                                    $row->target . ($row->port ? ':' . $row->port : null),
                                                    urlencode("\r\n\r\n"),
                                                    url('monitor/' . $row->monitor_id)
                                                )
                                            )
                                        );
                                    } catch (\Exception $exception) {
                                        error_log($exception->getMessage());
                                    }

                                    break;

                                case 'microsoft_teams':

                                    try {
                                        \Unirest\Request::post(
                                            $notification_handler->settings->microsoft_teams,
                                            ['Content-Type' => 'application/json'],
                                            \Unirest\Request\Body::json([
                                                'text' => sprintf(
                                                    l('monitor.simple_notification.is_not_ok', $row->language),
                                                    $row->name,
                                                    $row->target . ($row->port ? ':' . $row->port : null),
                                                    "\r\n\r\n",
                                                    url('monitor/' . $row->monitor_id)
                                                ),
                                            ])
                                        );
                                    } catch (\Exception $exception) {
                                        error_log($exception->getMessage());
                                    }

                                    break;

                                case 'x':

                                    $twitter = new \Abraham\TwitterOAuth\TwitterOAuth(
                                        $notification_handler->settings->x_consumer_key,
                                        $notification_handler->settings->x_consumer_secret,
                                        $notification_handler->settings->x_access_token,
                                        $notification_handler->settings->x_access_token_secret
                                    );

                                    $twitter->setApiVersion('2');

                                    try {
                                        $response = $twitter->post('tweets', ['text' => sprintf(
                                            l('monitor.simple_notification.is_not_ok', $row->language),
                                            $row->name,
                                            $row->target . ($row->port ? ':' . $row->port : null),
                                            "\r\n\r\n",
                                            url('monitor/' . $row->monitor_id)
                                        )]);
                                    } catch (\Exception $exception) {
                                        /* :* */
                                    }

                                    break;

                                case 'twilio':

                                    try {
                                        \Unirest\Request::auth(settings()->notification_handlers->twilio_sid, settings()->notification_handlers->twilio_token);

                                        \Unirest\Request::post(
                                            sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', settings()->notification_handlers->twilio_sid),
                                            [],
                                            [
                                                'From' => settings()->notification_handlers->twilio_number,
                                                'To' => $notification_handler->settings->twilio,
                                                'Body' => sprintf(
                                                    l('monitor.simple_notification.is_not_ok', $row->language),
                                                    $row->name,
                                                    $row->target . ($row->port ? ':' . $row->port : null),
                                                    "\r\n\r\n",
                                                    url('monitor/' . $row->monitor_id)
                                                ),
                                            ]
                                        );
                                    } catch (\Exception $exception) {
                                        error_log($exception->getMessage());
                                    }

                                    \Unirest\Request::auth('', '');

                                    break;

                                case 'twilio_call':

                                    try {
                                        \Unirest\Request::auth(settings()->notification_handlers->twilio_sid, settings()->notification_handlers->twilio_token);

                                        \Unirest\Request::post(
                                            sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Calls.json', settings()->notification_handlers->twilio_sid),
                                            [],
                                            [
                                                'From' => settings()->notification_handlers->twilio_number,
                                                'To' => $notification_handler->settings->twilio_call,
                                                'Url' => SITE_URL . 'twiml/monitor.simple_notification.is_not_ok?param1=' . urlencode($row->name) . '&param2=' . urlencode($row->target . ($row->port ? ':' . $row->port : null)) . '&param3=&param4=' . urlencode(url('monitor/' . $row->monitor_id)),
                                            ]
                                        );
                                    } catch (\Exception $exception) {
                                        error_log($exception->getMessage());
                                    }

                                    \Unirest\Request::auth('', '');

                                    break;

                                case 'whatsapp':

                                    try {
                                        \Unirest\Request::post(
                                            'https://graph.facebook.com/v18.0/' . settings()->notification_handlers->whatsapp_number_id . '/messages',
                                            [
                                                'Authorization' => 'Bearer ' . settings()->notification_handlers->whatsapp_access_token,
                                                'Content-Type' => 'application/json'
                                            ],
                                            \Unirest\Request\Body::json([
                                                'messaging_product' => 'whatsapp',
                                                'to' => $notification_handler->settings->whatsapp,
                                                'type' => 'template',
                                                'template' => [
                                                    'name' => 'monitor_down',
                                                    'language' => [
                                                        'code' => \Altum\Language::$default_code
                                                    ],
                                                    'components' => [[
                                                        'type' => 'body',
                                                        'parameters' => [
                                                            [
                                                                'type' => 'text',
                                                                'text' => $row->name
                                                            ],
                                                            [
                                                                'type' => 'text',
                                                                'text' => $row->target . ($row->port ? ':' . $row->port : null)
                                                            ],
                                                            [
                                                                'type' => 'text',
                                                                'text' => url('monitor/' . $row->monitor_id)
                                                            ],
                                                        ]
                                                    ]]

                                                ]
                                            ])
                                        );
                                    } catch (\Exception $exception) {
                                        error_log($exception->getMessage());
                                    }

                                    break;

                                case 'push_subscriber_id':
                                    $push_subscriber = db()->where('push_subscriber_id', $notification_handler->settings->push_subscriber_id)->getOne('push_subscribers');
                                    if(!$push_subscriber) {
                                        db()->where('notification_handler_id', $notification_handler->notification_handler_id)->update('notification_handlers', ['is_enabled' => 0]);
                                    };

                                    /* Prepare the web push */
                                    $push_notification = \Altum\Helpers\PushNotifications::send([
                                        'title' => l('monitor.push_notification.is_not_ok.title', $row->language),
                                        'description' => sprintf(l('monitor.push_notification.description', $row->language), $row->name, $row->target . ($row->port ? ':' . $row->port : null)),
                                        'url' => url('monitor/' . $row->monitor_id),
                                    ], $push_subscriber);

                                    /* Unsubscribe if push failed */
                                    if(!$push_notification) {
                                        db()->where('push_subscriber_id', $push_subscriber->push_subscriber_id)->delete('push_subscribers');
                                        db()->where('notification_handler_id', $notification_handler->notification_handler_id)->update('notification_handlers', ['is_enabled' => 0]);
                                    }

                                    break;

                            }
                        }
                    }

                    /* Close incident */
                    if($check['is_ok'] && $row->incident_id) {

                        /* Get the language for the user and set the timezone */
                        \Altum\Date::$timezone = $row->timezone;

                        /* Database query */
                        db()->where('incident_id', $row->incident_id)->update('incidents', [
                            'monitor_id' => $row->monitor_id,
                            'end_monitor_log_id' => $monitor_log_id,
                            'end_datetime' => get_date()
                        ]);

                        $incident_id = null;

                        /* Get details about the incident */
                        $monitor_incident = db()->where('incident_id', $row->incident_id)->getOne('incidents', ['start_datetime', 'end_datetime']);

                        /* Processing the notification handlers */
                        foreach($notification_handlers as $notification_handler) {
                            if(!$notification_handler->is_enabled) continue;
                            if(!in_array($notification_handler->notification_handler_id, $row->notifications->is_ok)) continue;

                            switch($notification_handler->type) {
                                case 'email':

                                    /* Prepare the email title */
                                    $email_title = sprintf(l('cron.is_ok.title', $row->language), $row->name);

                                    /* Prepare the View for the email content */
                                    $data = [
                                        'monitor_incident' => $monitor_incident,
                                        'row' => $row
                                    ];

                                    $email_content = (new \Altum\View('partials/cron/monitor_is_ok', (array) $this))->run($data);


                                    /* Send the email */
                                    send_mail($notification_handler->settings->email, $email_title, $email_content, ['anti_phishing_code' => $row->anti_phishing_code, 'language' => $row->language]);

                                    break;

                                case 'webhook':

                                    fire_and_forget('post', $notification_handler->settings->webhook, [
                                        'monitor_id' => $row->monitor_id,
                                        'name' => $row->name,
                                        'is_ok' => $check['is_ok'],
                                        'url' => url('monitor/' . $row->monitor_id)
                                    ]);

                                    break;

                                case 'slack':

                                    try {
                                        \Unirest\Request::post(
                                            $notification_handler->settings->slack,
                                            ['Accept' => 'application/json'],
                                            \Unirest\Request\Body::json([
                                                'text' => sprintf(
                                                    l('monitor.simple_notification.is_ok', $row->language),
                                                    $row->name,
                                                    $row->target . ($row->port ? ':' . $row->port : null),
                                                    "\r\n\r\n",
                                                    url('monitor/' . $row->monitor_id)
                                                ),
                                                'username' => settings()->main->title,
                                                'icon_emoji' => ':large_green_circle:'
                                            ])
                                        );
                                    } catch (\Exception $exception) {
                                        error_log($exception->getMessage());
                                    }

                                    break;

                                case 'discord':

                                    try {
                                        fire_and_forget(
                                            'POST',
                                            $notification_handler->settings->discord,
                                            [
                                                'embeds' => [
                                                    [
                                                        'title' => sprintf(
                                                            l('monitor.simple_notification.is_ok', $row->language),
                                                            $row->name,
                                                            $row->target . ($row->port ? ':' . $row->port : null),
                                                            "\r\n\r\n",
                                                            url('monitor/' . $row->monitor_id)
                                                        ),
                                                        'color' => '2664261',
                                                    ]
                                                ],
                                            ],
                                            'json',
                                            [
                                                'Accept' => 'application/json',
                                                'Content-Type' => 'application/json',
                                            ],
                                        );
                                    } catch (\Exception $exception) {
                                        error_log($exception->getMessage());
                                    }

                                    break;

                                case 'telegram':

                                    try {
                                        fire_and_forget(
                                            'GET',
                                            sprintf(
                                                'https://api.telegram.org/bot%s/sendMessage?chat_id=%s&text=%s',
                                                $notification_handler->settings->telegram,
                                                $notification_handler->settings->telegram_chat_id,
                                                sprintf(
                                                    l('monitor.simple_notification.is_ok', $row->language),
                                                    $row->name,
                                                    $row->target . ($row->port ? ':' . $row->port : null),
                                                    urlencode("\r\n\r\n"),
                                                    url('monitor/' . $row->monitor_id)
                                                )
                                            )
                                        );
                                    } catch (\Exception $exception) {
                                        error_log($exception->getMessage());
                                    }

                                    break;

                                case 'microsoft_teams':

                                    try {
                                        \Unirest\Request::post(
                                            $notification_handler->settings->microsoft_teams,
                                            ['Content-Type' => 'application/json'],
                                            \Unirest\Request\Body::json([
                                                'text' => sprintf(
                                                    l('monitor.simple_notification.is_ok', $row->language),
                                                    $row->name,
                                                    $row->target . ($row->port ? ':' . $row->port : null),
                                                    "\r\n\r\n",
                                                    url('monitor/' . $row->monitor_id)
                                                ),
                                            ])
                                        );
                                    } catch (\Exception $exception) {
                                        error_log($exception->getMessage());
                                    }

                                    break;

                                case 'x':

                                    $twitter = new \Abraham\TwitterOAuth\TwitterOAuth(
                                        $notification_handler->settings->x_consumer_key,
                                        $notification_handler->settings->x_consumer_secret,
                                        $notification_handler->settings->x_access_token,
                                        $notification_handler->settings->x_access_token_secret
                                    );

                                    $twitter->setApiVersion('2');

                                    try {
                                        $response = $twitter->post('tweets', ['text' => sprintf(
                                            l('monitor.simple_notification.is_ok', $row->language),
                                            $row->name,
                                            $row->target . ($row->port ? ':' . $row->port : null),
                                            "\r\n\r\n",
                                            url('monitor/' . $row->monitor_id)
                                        )]);
                                    } catch (\Exception $exception) {
                                        /* :* */
                                    }

                                    break;

                                case 'twilio':

                                    try {
                                        \Unirest\Request::auth(settings()->notification_handlers->twilio_sid, settings()->notification_handlers->twilio_token);

                                        \Unirest\Request::post(
                                            sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', settings()->notification_handlers->twilio_sid),
                                            [],
                                            [
                                                'From' => settings()->notification_handlers->twilio_number,
                                                'To' => $notification_handler->settings->twilio,
                                                'Body' => sprintf(
                                                    l('monitor.simple_notification.is_ok', $row->language),
                                                    $row->name,
                                                    $row->target . ($row->port ? ':' . $row->port : null),
                                                    "\r\n\r\n",
                                                    url('monitor/' . $row->monitor_id)
                                                ),
                                            ]
                                        );
                                    } catch (\Exception $exception) {
                                        error_log($exception->getMessage());
                                    }

                                    \Unirest\Request::auth('', '');

                                    break;

                                case 'twilio_call':

                                    try {
                                        \Unirest\Request::auth(settings()->notification_handlers->twilio_sid, settings()->notification_handlers->twilio_token);

                                        \Unirest\Request::post(
                                            sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Calls.json', settings()->notification_handlers->twilio_sid),
                                            [],
                                            [
                                                'From' => settings()->notification_handlers->twilio_number,
                                                'To' => $notification_handler->settings->twilio_call,
                                                'Url' => SITE_URL . 'twiml/monitor.simple_notification.is_ok?param1=' . urlencode($row->name) . '&param2=' . urlencode($row->target) . ($row->port ? ':' . $row->port : null) . '&param3=&param4=' . urlencode(url('monitor/' . $row->monitor_id)),
                                            ]
                                        );
                                    } catch (\Exception $exception) {
                                        error_log($exception->getMessage());
                                    }

                                    \Unirest\Request::auth('', '');

                                    break;

                                case 'whatsapp':

                                    try {
                                        $test = \Unirest\Request::post(
                                            'https://graph.facebook.com/v18.0/' . settings()->notification_handlers->whatsapp_number_id . '/messages',
                                            [
                                                'Authorization' => 'Bearer ' . settings()->notification_handlers->whatsapp_access_token,
                                                'Content-Type' => 'application/json'
                                            ],
                                            \Unirest\Request\Body::json([
                                                'messaging_product' => 'whatsapp',
                                                'to' => $notification_handler->settings->whatsapp,
                                                'type' => 'template',
                                                'template' => [
                                                    'name' => 'monitor_up',
                                                    'language' => [
                                                        'code' => \Altum\Language::$default_code
                                                    ],
                                                    'components' => [[
                                                        'type' => 'body',
                                                        'parameters' => [
                                                            [
                                                                'type' => 'text',
                                                                'text' => $row->name
                                                            ],
                                                            [
                                                                'type' => 'text',
                                                                'text' => $row->target . ($row->port ? ':' . $row->port : null)
                                                            ],
                                                            [
                                                                'type' => 'text',
                                                                'text' => url('monitor/' . $row->monitor_id)
                                                            ],
                                                        ]
                                                    ]]

                                                ]
                                            ])
                                        );
                                    } catch (\Exception $exception) {
                                        error_log($exception->getMessage());
                                    }


                                    break;

                                case 'push_subscriber_id':
                                    $push_subscriber = db()->where('push_subscriber_id', $notification_handler->settings->push_subscriber_id)->getOne('push_subscribers');
                                    if(!$push_subscriber) {
                                        db()->where('notification_handler_id', $notification_handler->notification_handler_id)->update('notification_handlers', ['is_enabled' => 0]);
                                    };

                                    /* Prepare the web push */
                                    $push_notification = \Altum\Helpers\PushNotifications::send([
                                        'title' => l('monitor.push_notification.is_ok.title', $row->language),
                                        'description' => sprintf(l('monitor.push_notification.description', $row->language), $row->name, $row->target . ($row->port ? ':' . $row->port : null)),
                                        'url' => url('monitor/' . $row->monitor_id),
                                    ], $push_subscriber);

                                    /* Unsubscribe if push failed */
                                    if(!$push_notification) {
                                        db()->where('push_subscriber_id', $push_subscriber->push_subscriber_id)->delete('push_subscribers');
                                        db()->where('notification_handler_id', $notification_handler->notification_handler_id)->update('notification_handlers', ['is_enabled' => 0]);
                                    }

                                    break;
                            }
                        }
                    }

                    /* Update the monitor */
                    db()->where('monitor_id', $row->monitor_id)->update('monitors', [
                        'incident_id' => $incident_id,
                        'is_ok' => $check['is_ok'],
                        'uptime' => $vars['uptime'],
                        'uptime_seconds' => $vars['uptime_seconds'],
                        'downtime' => $vars['downtime'],
                        'downtime_seconds' => $vars['downtime_seconds'],
                        'average_response_time' => $vars['average_response_time'],
                        'total_checks' => db()->inc(),
                        'total_ok_checks' => $vars['total_ok_checks'],
                        'total_not_ok_checks' => $vars['total_not_ok_checks'],
                        'last_check_datetime' => $vars['last_check_datetime'],
                        'next_check_datetime' => $vars['next_check_datetime'],
                        'main_ok_datetime' => $vars['main_ok_datetime'],
                        'last_ok_datetime' => $vars['last_ok_datetime'],
                        'main_not_ok_datetime' => $vars['main_not_ok_datetime'],
                        'last_not_ok_datetime' => $vars['last_not_ok_datetime'],
                        'last_logs' => $vars['last_logs'],
                    ]);

                    /* Clear out old monitor logs */
                    if($row->plan_settings->logs_retention != -1) {
                        $x_days_ago_datetime = (new \DateTime())->modify('-' . ($row->plan_settings->logs_retention ?? 90) . ' days')->format('Y-m-d H:i:s');
                        database()->query("DELETE FROM `monitors_logs` WHERE `datetime` < '{$x_days_ago_datetime}' AND `user_id` = {$row->user_id}");
                    }

                    /* Clear the cache */
                    cache()->deleteItemsByTag('monitor_id=' . $row->monitor_id);

                    return $row->monitor_id;
                };
            }

            /* Randomize the callables */
            shuffle($callables);

            /* Only allow the maximum checks for this run */
            $callables = array_slice($callables, 0, $checks_limit);

            $time_start = microtime(true);

            if(php_sapi_name() == 'cli') {
                $results = \Spatie\Fork\Fork::new()
                    ->before(function () { \Altum\Database::initialize(); })
                    ->after(function () { \Altum\Database::close(); })
                    ->run(...$callables);
            } else {
                foreach($callables as $callable) {
                    $callable();
                }
            }

            echo 'Checks finished in ' . (microtime(true) - $time_start) . ' seconds.';
            \Altum\Database::close();
            \Altum\Database::initialize();
        }

        $this->close();
    }

    public function heartbeats() {

        if(!settings()->monitors_heartbeats->heartbeats_is_enabled) {
            return;
        }

        $this->initiate();

        $date = get_date();

        /* Update cron job last run date */
        $this->update_cron_execution_datetimes('heartbeats_datetime');

        for($i = 1; $i <= 1000; $i++) {
            $row = database()->query("
                SELECT
                    `heartbeats`.*,
                       
                    `users`.`email`,
                    `users`.`plan_settings`,
                    `users`.`language`,
                    `users`.`timezone`,
                    `users`.`anti_phishing_code`
                FROM 
                    `heartbeats`
                LEFT JOIN 
                    `users` ON `heartbeats`.`user_id` = `users`.`user_id` 
                WHERE 
                    `heartbeats`.`is_enabled` = 1
                    AND `heartbeats`.`next_run_datetime` <= '{$date}' 
                    AND `users`.`status` = 1
                LIMIT 1
            ")->fetch_object();

            /* Break if no results */
            if(!$row) break;

            if(DEBUG) printf('Going through %s heartbeat..<br />', $row->name);

            $row->plan_settings = json_decode($row->plan_settings);
            $row->settings = json_decode($row->settings ?? '');
            $row->notifications = json_decode($row->notifications ?? '');
            $row->last_logs = json_decode($row->last_logs ?? '');

            /* Get available notification handlers */
            $notification_handlers = (new \Altum\Models\NotificationHandlers())->get_notification_handlers_by_user_id($row->user_id);

            /* Since the result is here, the cron is not working */
            $is_ok = 0;

            /* Insert the history log */
            $heartbeat_log_id = db()->insert('heartbeats_logs', [
                'heartbeat_id' => $row->heartbeat_id,
                'user_id' => $row->user_id,
                'is_ok' => $is_ok,
                'datetime' => get_date(),
            ]);

            /* Assuming, based on the run interval */
            $downtime_seconds_to_add = 0;
            switch($row->settings->run_interval_type) {
                case 'minutes':
                    $downtime_seconds_to_add = $row->settings->run_interval * 60;
                    break;

                case 'hours':
                    $downtime_seconds_to_add = $row->settings->run_interval * 60 * 60;
                    break;

                case 'days':
                    $downtime_seconds_to_add = $row->settings->run_interval * 60 * 60 * 24;
                    break;
            }
            $uptime_seconds = $row->uptime_seconds;
            $downtime_seconds = $row->downtime_seconds + $downtime_seconds_to_add;

            /* ^_^ */
            $uptime = $uptime_seconds > 0 ? $uptime_seconds / ($uptime_seconds + $downtime_seconds) * 100 : 0;
            $downtime = 100 - $uptime;
            $main_missed_datetime = $row->is_ok && !$is_ok ? get_date() : $row->main_missed_datetime;
            $last_missed_datetime = get_date();

            /* Calculate expected next run */
            $next_run_datetime = (new \DateTime())
                ->modify('+' . $row->settings->run_interval . ' ' . $row->settings->run_interval_type)
                ->modify('+' . $row->settings->run_interval_grace . ' ' . $row->settings->run_interval_grace_type)
                ->format('Y-m-d H:i:s');

            /* Create / update an incident if needed */
            $incident_id = $row->incident_id;

            if(!$is_ok && !$row->incident_id) {

                /* Database query */
                $incident_id = db()->insert('incidents', [
                    'user_id' => $row->user_id,
                    'heartbeat_id' => $row->heartbeat_id,
                    'start_heartbeat_log_id' => $heartbeat_log_id,
                    'start_datetime' => get_date(),
                ]);

                /* Get the language for the user and set the timezone */
                \Altum\Date::$timezone = $row->timezone;

                /* Processing the notification handlers */
                foreach($notification_handlers as $notification_handler) {
                    if(!$notification_handler->is_enabled) continue;
                    if(!in_array($notification_handler->notification_handler_id, $row->notifications->is_ok)) continue;

                    switch($notification_handler->type) {
                        case 'email':

                            /* Prepare the email title */
                            $email_title = sprintf(l('cron.is_not_ok.title', $row->language), $row->name);

                            /* Prepare the View for the email content */
                            $data = [
                                'row' => $row
                            ];

                            $email_content = (new \Altum\View('partials/cron/heartbeat_is_not_ok', (array) $this))->run($data);

                            /* Send the email */
                            send_mail($notification_handler->settings->email, $email_title, $email_content, ['anti_phishing_code' => $row->anti_phishing_code, 'language' => $row->language]);

                            break;

                        case 'webhook':

                            fire_and_forget('post', $notification_handler->settings->webhook, [
                                'heartbeat_id' => $row->heartbeat_id,
                                'name' => $row->name,
                                'is_ok' => $is_ok,
                                'url' => url('heartbeat/' . $row->heartbeat_id)
                            ]);

                            break;

                        case 'slack':

                            try {
                                \Unirest\Request::post(
                                    $notification_handler->settings->slack,
                                    ['Accept' => 'application/json'],
                                    \Unirest\Request\Body::json([
                                        'text' => sprintf(
                                            l('heartbeat.simple_notification.is_not_ok', $row->language),
                                            $row->name,
                                            "\r\n\r\n",
                                            url('heartbeat/' . $row->heartbeat_id)
                                        ),
                                        'username' => settings()->main->title,
                                        'icon_emoji' => ':large_red_square:'
                                    ])
                                );
                            } catch (\Exception $exception) {
                                error_log($exception->getMessage());
                            }

                            break;

                        case 'discord':

                            try {
                                fire_and_forget(
                                    'POST',
                                    $notification_handler->settings->discord,
                                    [
                                        'embeds' => [
                                            [
                                                'title' => sprintf(
                                                    l('heartbeat.simple_notification.is_not_ok', $row->language),
                                                    $row->name,
                                                    "\r\n\r\n",
                                                    url('heartbeat/' . $row->heartbeat_id)
                                                ),
                                                'color' => '14431557',
                                            ]
                                        ],
                                    ],
                                    'json',
                                    [
                                        'Accept' => 'application/json',
                                        'Content-Type' => 'application/json',
                                    ],
                                );
                            } catch (\Exception $exception) {
                                error_log($exception->getMessage());
                            }

                            break;

                        case 'telegram':

                            try {
                                fire_and_forget(
                                    'GET',
                                    sprintf(
                                        'https://api.telegram.org/bot%s/sendMessage?chat_id=%s&text=%s',
                                        $notification_handler->settings->telegram,
                                        $notification_handler->settings->telegram_chat_id,
                                        sprintf(
                                            l('heartbeat.simple_notification.is_not_ok', $row->language),
                                            $row->name,
                                            urlencode("\r\n\r\n"),
                                            url('heartbeat/' . $row->heartbeat_id)
                                        )
                                    )
                                );
                            } catch (\Exception $exception) {
                                error_log($exception->getMessage());
                            }

                            break;

                        case 'microsoft_teams':

                            try {
                                \Unirest\Request::post(
                                    $notification_handler->settings->microsoft_teams,
                                    ['Content-Type' => 'application/json'],
                                    \Unirest\Request\Body::json([
                                        'text' => sprintf(
                                            l('heartbeat.simple_notification.is_not_ok', $row->language),
                                            $row->name,
                                            "\r\n\r\n",
                                            url('heartbeat/' . $row->heartbeat_id)
                                        ),
                                    ])
                                );
                            } catch (\Exception $exception) {
                                error_log($exception->getMessage());
                            }

                            break;

                        case 'x':

                            $twitter = new \Abraham\TwitterOAuth\TwitterOAuth(
                                $notification_handler->settings->x_consumer_key,
                                $notification_handler->settings->x_consumer_secret,
                                $notification_handler->settings->x_access_token,
                                $notification_handler->settings->x_access_token_secret
                            );

                            $twitter->setApiVersion('2');

                            try {
                                $response = $twitter->post('tweets', ['text' => sprintf(
                                    l('heartbeat.simple_notification.is_not_ok', $row->language),
                                    $row->name,
                                    "\r\n\r\n",
                                    url('heartbeat/' . $row->heartbeat_id)
                                )]);
                            } catch (\Exception $exception) {
                                /* :* */
                            }

                            break;

                        case 'twilio':

                            try {
                                \Unirest\Request::auth(settings()->notification_handlers->twilio_sid, settings()->notification_handlers->twilio_token);

                                \Unirest\Request::post(
                                    sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', settings()->notification_handlers->twilio_sid),
                                    [],
                                    [
                                        'From' => settings()->notification_handlers->twilio_number,
                                        'To' => $notification_handler->settings->twilio,
                                        'Body' => sprintf(
                                            l('heartbeat.simple_notification.is_not_ok', $row->language),
                                            $row->name,
                                            "\r\n\r\n",
                                            url('heartbeat/' . $row->heartbeat_id)
                                        ),
                                    ]
                                );
                            } catch (\Exception $exception) {
                                error_log($exception->getMessage());
                            }

                            \Unirest\Request::auth('', '');

                            break;

                        case 'twilio_call':

                            try {
                                \Unirest\Request::auth(settings()->notification_handlers->twilio_sid, settings()->notification_handlers->twilio_token);

                                \Unirest\Request::post(
                                    sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Calls.json', settings()->notification_handlers->twilio_sid),
                                    [],
                                    [
                                        'From' => settings()->notification_handlers->twilio_number,
                                        'To' => $notification_handler->settings->twilio_call,
                                        'Url' => SITE_URL . 'twiml/heartbeat.simple_notification.is_not_ok?param1=' . urlencode($row->name) . '&param2=&param3=' . url('heartbeat/' . $row->heartbeat_id),
                                    ]
                                );
                            } catch (\Exception $exception) {
                                error_log($exception->getMessage());
                            }

                            \Unirest\Request::auth('', '');

                            break;

                        case 'whatsapp':

                            try {
                                \Unirest\Request::post(
                                    'https://graph.facebook.com/v18.0/' . settings()->notification_handlers->whatsapp_number_id . '/messages',
                                    [
                                        'Authorization' => 'Bearer ' . settings()->notification_handlers->whatsapp_access_token,
                                        'Content-Type' => 'application/json'
                                    ],
                                    \Unirest\Request\Body::json([
                                        'messaging_product' => 'whatsapp',
                                        'to' => $notification_handler->settings->whatsapp,
                                        'type' => 'template',
                                        'template' => [
                                            'name' => 'heartbeat_down',
                                            'language' => [
                                                'code' => \Altum\Language::$default_code
                                            ],
                                            'components' => [[
                                                'type' => 'body',
                                                'parameters' => [
                                                    [
                                                        'type' => 'text',
                                                        'text' => $row->name
                                                    ],
                                                    [
                                                        'type' => 'text',
                                                        'text' => url('heartbeat/' . $row->heartbeat_id)
                                                    ],
                                                ]
                                            ]]

                                        ]
                                    ])
                                );
                            } catch (\Exception $exception) {
                                error_log($exception->getMessage());
                            }

                            break;

                        case 'push_subscriber_id':
                            $push_subscriber = db()->where('push_subscriber_id', $notification_handler->settings->push_subscriber_id)->getOne('push_subscribers');
                            if(!$push_subscriber) {
                                db()->where('notification_handler_id', $notification_handler->notification_handler_id)->update('notification_handlers', ['is_enabled' => 0]);
                            };

                            /* Prepare the web push */
                            $push_notification = \Altum\Helpers\PushNotifications::send([
                                'title' => l('heartbeat.push_notification.is_not_ok.title', $row->language),
                                'description' => sprintf(l('heartbeat.push_notification.description', $row->language), $row->name, $row->target),
                                'url' => url('heartbeat/' . $row->heartbeat_id),
                            ], $push_subscriber);

                            /* Unsubscribe if push failed */
                            if(!$push_notification) {
                                db()->where('push_subscriber_id', $push_subscriber->push_subscriber_id)->delete('push_subscribers');
                                db()->where('notification_handler_id', $notification_handler->notification_handler_id)->update('notification_handlers', ['is_enabled' => 0]);
                            }

                            break;
                    }
                }
            }

            /* Keep the last logs for immediate access */
            $last_logs = [];

            for($i = 1; $i <= 6; $i++) {
                $last_logs[] = isset($row->last_logs[$i]) ? $row->last_logs[$i] : [];
            }

            $last_logs[] = [
                'is_ok' => $is_ok,
                'datetime' => get_date(),
            ];

            /* Update the heartbeat */
            db()->where('heartbeat_id', $row->heartbeat_id)->update('heartbeats', [
                'incident_id' => $incident_id,
                'is_ok' => $is_ok,
                'uptime' => $uptime,
                'uptime_seconds' => $uptime_seconds,
                'downtime' => $downtime,
                'downtime_seconds' => $downtime_seconds,
                'total_missed_runs' => db()->inc(),
                'main_missed_datetime' => $main_missed_datetime,
                'last_missed_datetime' => $last_missed_datetime,
                'next_run_datetime' => $next_run_datetime,
                'last_logs' => json_encode($last_logs),
            ]);

            /* Clear out old heartbeats logs */
            if($row->plan_settings->logs_retention != -1) {
                $x_days_ago_datetime = (new \DateTime())->modify('-' . ($row->plan_settings->logs_retention ?? 90) . ' days')->format('Y-m-d H:i:s');
                database()->query("DELETE FROM `heartbeats_logs` WHERE `datetime` < '{$x_days_ago_datetime}' AND `user_id` = {$row->user_id}");
            }

            /* Clear the cache */
            cache()->deleteItemsByTag('heartbeat_id=' . $row->heartbeat_id);

        }

        $this->close();
    }

    public function domain_names() {

        if(!settings()->monitors_heartbeats->domain_names_is_enabled) {
            return;
        }

        $this->initiate();

        $date = get_date();

        /* Update cron job last run date */
        $this->update_cron_execution_datetimes('domain_names_datetime');

        for($i = 1; $i <= 1000; $i++) {
            $row = database()->query("
                SELECT
                    `domain_names`.*,
                    `users`.`email`,
                    `users`.`plan_settings`,
                    `users`.`language`,
                    `users`.`timezone`,
                    `users`.`anti_phishing_code`
                FROM 
                    `domain_names`
                LEFT JOIN 
                    `users` ON `domain_names`.`user_id` = `users`.`user_id` 
                WHERE 
                    `domain_names`.`is_enabled` = 1
                    AND `domain_names`.`next_check_datetime` <= '{$date}' 
                    AND `users`.`status` = 1
                ORDER BY `domain_names`.`next_check_datetime`
                LIMIT 1
            ")->fetch_object();

            /* Break if no results */
            if(!$row) break;

            if(DEBUG) printf('Going through %s (%s) domain name..<br />', $row->name, $row->target);

            $row->plan_settings = json_decode($row->plan_settings ?? '');
            $row->whois_notifications = json_decode($row->whois_notifications ?? '');
            $row->ssl_notifications = json_decode($row->ssl_notifications ?? '');
            $row->ssl = json_decode($row->ssl ?? '');
            $row->whois = json_decode($row->whois ?? '');

            /* Get available notification handlers */
            $notification_handlers = (new \Altum\Models\NotificationHandlers())->get_notification_handlers_by_user_id($row->user_id);

            /* Check the domain name whois */
            try {
                $get_whois = \Iodev\Whois\Factory::get()->createWhois();
                $whois_info = $get_whois->loadDomainInfo(get_domain_from_host($row->target));
            } catch (\Exception $e) {
                //
            }

            $whois = isset($whois_info) && $whois_info ? [
                'start_datetime' => $whois_info->creationDate ? (new \DateTime())->setTimestamp($whois_info->creationDate)->format('Y-m-d H:i:s') : null,
                'updated_datetime' => $whois_info->updatedDate ? (new \DateTime())->setTimestamp($whois_info->updatedDate)->format('Y-m-d H:i:s') : null,
                'end_datetime' => $whois_info->expirationDate ? (new \DateTime())->setTimestamp($whois_info->expirationDate)->format('Y-m-d H:i:s') : null,
                'registrar' => $whois_info->registrar,
                'nameservers' => $whois_info->nameServers,
            ] : [];

            /* Check for an SSL certificate */
            $certificate = get_website_certificate('https://' . $row->target, $row->ssl_port ?? 443);

            /* Create the new SSL object */
            $ssl = [];
            if($certificate) {
                $ssl = $certificate;
            }

            /* Get the language for the user and set the timezone */
            \Altum\Date::$timezone = $row->timezone;

            /* Calculate timings */
            $whois_expires_in_days = isset($whois['end_datetime']) ? (new \DateTime($whois['end_datetime']))->diff(new \DateTime())->days : null;
            $ssl_expires_in_days = isset($ssl['end_datetime']) ? (new \DateTime($ssl['end_datetime']))->diff(new \DateTime())->days : null;

            $whois_notification_every_x_day = (int) floor($row->whois_notifications->whois_notifications_timing / 3);
            $whois_notification_every_x_day = !$whois_notification_every_x_day ? 1 : $whois_notification_every_x_day;

            $ssl_notification_every_x_day = (int) floor($row->ssl_notifications->ssl_notifications_timing / 3);
            $ssl_notification_every_x_day = !$ssl_notification_every_x_day ? 1 : $ssl_notification_every_x_day;

            /* Processing the notification handlers */
            foreach($notification_handlers as $notification_handler) {
                if(!$notification_handler->is_enabled) continue;

                switch($notification_handler->type) {
                    case 'email':

                        if(in_array($notification_handler->notification_handler_id, $row->whois_notifications->whois_notifications) && $whois) {
                            if(
                                $whois_expires_in_days
                                && (new \DateTime($whois['end_datetime'])) >= (new \DateTime())
                                && $whois_expires_in_days <= $row->whois_notifications->whois_notifications_timing
                                && (
                                    !isset($row->whois->last_notification_datetime)
                                    || ($row->whois->last_notification_datetime && (new \DateTime($row->whois->last_notification_datetime))->diff(new \DateTime())->days > $whois_notification_every_x_day)
                                )
                            ) {

                                /* Prepare the email title */
                                $email_title = sprintf(l('domain_name.email_notifications.whois.title', $row->language), $row->name, $row->target, $whois_expires_in_days);

                                /* Prepare the View for the email content */
                                $data = [
                                    'row' => $row,
                                    'whois_expires_in_days' => $whois_expires_in_days,
                                    'whois_end_datetime' => \Altum\Date::get($whois['end_datetime']),
                                    'timezone' => $row->timezone,
                                ];

                                $email_content = (new \Altum\View('domain-name/domain_name_whois_notification', (array) $this))->run($data);

                                /* Send the email */
                                send_mail($notification_handler->settings->email, $email_title, $email_content);

                            }
                        }

                        if(in_array($notification_handler->notification_handler_id, $row->ssl_notifications->ssl_notifications) && $ssl) {
                            if(
                                $ssl_expires_in_days
                                && (new \DateTime($ssl['end_datetime'])) >= (new \DateTime())
                                && $ssl_expires_in_days <= $row->ssl_notifications->ssl_notifications_timing
                                && (
                                    !isset($row->ssl->last_notification_datetime)
                                    || ($row->ssl->last_notification_datetime && (new \DateTime($row->ssl->last_notification_datetime))->diff(new \DateTime())->days > $ssl_notification_every_x_day)
                                )
                            ) {

                                /* Prepare the email title */
                                $email_title = sprintf(l('domain_name.email_notifications.ssl.title', $row->language), $row->name, $row->target, $ssl_expires_in_days);

                                /* Prepare the View for the email content */
                                $data = [
                                    'row' => $row,
                                    'ssl_expires_in_days' => $ssl_expires_in_days,
                                    'ssl_end_datetime' => $ssl['end_datetime'],
                                    'timezone' => $row->timezone,
                                ];

                                $email_content = (new \Altum\View('domain-name/domain_name_ssl_notification', (array) $this))->run($data);

                                /* Send the email */
                                send_mail($notification_handler->settings->email, $email_title, $email_content);

                            }
                        }


                        break;

                    case 'webhook':

                        if(in_array($notification_handler->notification_handler_id, $row->whois_notifications->whois_notifications) && $whois) {
                            if(
                                $whois_expires_in_days
                                && (new \DateTime($whois['end_datetime'])) >= (new \DateTime())
                                && $whois_expires_in_days <= $row->whois_notifications->whois_notifications_timing
                                && (
                                    !isset($row->whois->last_notification_datetime)
                                    || ($row->whois->last_notification_datetime && (new \DateTime($row->whois->last_notification_datetime))->diff(new \DateTime())->days > $whois_notification_every_x_day)
                                )
                            ) {

                                fire_and_forget('post', $notification_handler->settings->webhook, [
                                    'domain_name_id' => $row->domain_name_id,
                                    'name' => $row->name,
                                    'target' => $row->target,
                                    'whois_end_datetime' => \Altum\Date::get($whois['end_datetime']),
                                    'timezone' => $row->timezone,
                                    'url' => url('domain-name/' . $row->domain_name_id),
                                ]);

                            }
                        }

                        if(in_array($notification_handler->notification_handler_id, $row->ssl_notifications->ssl_notifications) && $ssl) {
                            if(
                                $ssl_expires_in_days
                                && (new \DateTime($ssl['end_datetime'])) >= (new \DateTime())
                                && $ssl_expires_in_days <= $row->ssl_notifications->ssl_notifications_timing
                                && (
                                    !isset($row->ssl->last_notification_datetime)
                                    || ($row->ssl->last_notification_datetime && (new \DateTime($row->ssl->last_notification_datetime))->diff(new \DateTime())->days > $ssl_notification_every_x_day)
                                )
                            ) {

                                try {
                                    \Unirest\Request::post($notification_handler->settings->webhook, [], [
                                        'domain_name_id' => $row->domain_name_id,
                                        'name' => $row->name,
                                        'target' => $row->target,
                                        'ssl_end_datetime' => \Altum\Date::get($ssl['end_datetime']),
                                        'timezone' => $row->timezone,
                                        'url' => url('domain-name/' . $row->domain_name_id),
                                    ]);
                                } catch (\Exception $exception) {
                                    error_log($exception->getMessage());
                                }

                            }
                        }

                        break;

                    case 'slack':

                        if(in_array($notification_handler->notification_handler_id, $row->whois_notifications->whois_notifications) && $whois) {
                            if(
                                $whois_expires_in_days
                                && (new \DateTime($whois['end_datetime'])) >= (new \DateTime())
                                && $whois_expires_in_days <= $row->whois_notifications->whois_notifications_timing
                                && (
                                    !isset($row->whois->last_notification_datetime)
                                    || ($row->whois->last_notification_datetime && (new \DateTime($row->whois->last_notification_datetime))->diff(new \DateTime())->days > $whois_notification_every_x_day)
                                )
                            ) {

                                try {
                                    \Unirest\Request::post(
                                        $notification_handler->settings->slack,
                                        ['Accept' => 'application/json'],
                                        \Unirest\Request\Body::json([
                                            'text' => sprintf(
                                                l('domain_name.simple_notification.whois', $row->language),
                                                $row->name,
                                                $row->target,
                                                $whois_expires_in_days,
                                                \Altum\Date::get($whois['end_datetime']),
                                                $row->timezone,
                                                "\r\n\r\n",
                                                url('domain-name/' . $row->domain_name_id)
                                            ),
                                            'username' => settings()->main->title
                                        ])
                                    );
                                } catch (\Exception $exception) {
                                    error_log($exception->getMessage());
                                }

                            }
                        }

                        if(in_array($notification_handler->notification_handler_id, $row->ssl_notifications->ssl_notifications) && $ssl) {
                            if(
                                $ssl_expires_in_days
                                && (new \DateTime($ssl['end_datetime'])) >= (new \DateTime())
                                && $ssl_expires_in_days <= $row->ssl_notifications->ssl_notifications_timing
                                && (
                                    !isset($row->ssl->last_notification_datetime)
                                    || ($row->ssl->last_notification_datetime && (new \DateTime($row->ssl->last_notification_datetime))->diff(new \DateTime())->days > $ssl_notification_every_x_day)
                                )
                            ) {

                                try {
                                    \Unirest\Request::post(
                                        $notification_handler->settings->slack,
                                        ['Accept' => 'application/json'],
                                        \Unirest\Request\Body::json([
                                            'text' => sprintf(
                                                l('domain_name.simple_notification.ssl', $row->language),
                                                $row->name,
                                                $row->target,
                                                $ssl_expires_in_days,
                                                \Altum\Date::get($ssl['end_datetime']),
                                                $row->timezone,
                                                "\r\n\r\n",
                                                url('domain-name/' . $row->domain_name_id)
                                            ),
                                            'username' => settings()->main->title
                                        ])
                                    );
                                } catch (\Exception $exception) {
                                    error_log($exception->getMessage());
                                }

                            }
                        }

                        break;

                    case 'discord':

                        if(in_array($notification_handler->notification_handler_id, $row->whois_notifications->whois_notifications) && $whois) {
                            if(
                                $whois_expires_in_days
                                && (new \DateTime($whois['end_datetime'])) >= (new \DateTime())
                                && $whois_expires_in_days <= $row->whois_notifications->whois_notifications_timing
                                && (
                                    !isset($row->whois->last_notification_datetime)
                                    || ($row->whois->last_notification_datetime && (new \DateTime($row->whois->last_notification_datetime))->diff(new \DateTime())->days > $whois_notification_every_x_day)
                                )
                            ) {

                                try {
                                    fire_and_forget(
                                        'POST',
                                        $notification_handler->settings->discord,
                                        [
                                            'embeds' => [
                                                [
                                                    'title' => sprintf(
                                                        l('domain_name.simple_notification.whois', $row->language),
                                                        $row->name,
                                                        $row->target,
                                                        $whois_expires_in_days,
                                                        \Altum\Date::get($whois['end_datetime']),
                                                        $row->timezone,
                                                        "\r\n\r\n",
                                                        url('domain-name/' . $row->domain_name_id)
                                                    ),
                                                ]
                                            ],
                                        ],
                                        'json',
                                        [
                                            'Accept' => 'application/json',
                                            'Content-Type' => 'application/json',
                                        ],
                                    );
                                } catch (\Exception $exception) {
                                    error_log($exception->getMessage());
                                }

                            }
                        }

                        if(in_array($notification_handler->notification_handler_id, $row->ssl_notifications->ssl_notifications) && $ssl) {
                            if(
                                $ssl_expires_in_days
                                && (new \DateTime($ssl['end_datetime'])) >= (new \DateTime())
                                && $ssl_expires_in_days <= $row->ssl_notifications->ssl_notifications_timing
                                && (
                                    !isset($row->ssl->last_notification_datetime)
                                    || ($row->ssl->last_notification_datetime && (new \DateTime($row->ssl->last_notification_datetime))->diff(new \DateTime())->days > $ssl_notification_every_x_day)
                                )
                            ) {

                                try {
                                    \Unirest\Request::post(
                                        $notification_handler->settings->discord,
                                        [
                                            'Accept' => 'application/json',
                                            'Content-Type' => 'application/json',
                                        ],
                                        \Unirest\Request\Body::json([
                                            'embeds' => [
                                                [
                                                    'title' => sprintf(
                                                        l('domain_name.simple_notification.ssl', $row->language),
                                                        $row->name,
                                                        $row->target,
                                                        $ssl_expires_in_days,
                                                        \Altum\Date::get($ssl['end_datetime']),
                                                        $row->timezone,
                                                        "\r\n\r\n",
                                                        url('domain-name/' . $row->domain_name_id)
                                                    ),
                                                ]
                                            ],
                                        ])
                                    );
                                } catch (\Exception $exception) {
                                    error_log($exception->getMessage());
                                }

                            }
                        }

                        break;

                    case 'telegram':

                        if(in_array($notification_handler->notification_handler_id, $row->whois_notifications->whois_notifications) && $whois) {
                            if(
                                $whois_expires_in_days
                                && (new \DateTime($whois['end_datetime'])) >= (new \DateTime())
                                && $whois_expires_in_days <= $row->whois_notifications->whois_notifications_timing
                                && (
                                    !isset($row->whois->last_notification_datetime)
                                    || ($row->whois->last_notification_datetime && (new \DateTime($row->whois->last_notification_datetime))->diff(new \DateTime())->days > $whois_notification_every_x_day)
                                )
                            ) {

                                try {
                                    fire_and_forget(
                                        'GET',
                                        sprintf(
                                            'https://api.telegram.org/bot%s/sendMessage?chat_id=%s&text=%s',
                                            $notification_handler->settings->telegram,
                                            $notification_handler->settings->telegram_chat_id,
                                            sprintf(
                                                l('domain_name.simple_notification.whois', $row->language),
                                                $row->name,
                                                $row->target,
                                                $whois_expires_in_days,
                                                \Altum\Date::get($whois['end_datetime']),
                                                $row->timezone,
                                                "\r\n\r\n",
                                                url('domain-name/' . $row->domain_name_id)
                                            )
                                        )
                                    );
                                } catch (\Exception $exception) {
                                    error_log($exception->getMessage());
                                }

                            }
                        }

                        if(in_array($notification_handler->notification_handler_id, $row->ssl_notifications->ssl_notifications) && $ssl) {
                            if(
                                $ssl_expires_in_days
                                && (new \DateTime($ssl['end_datetime'])) >= (new \DateTime())
                                && $ssl_expires_in_days <= $row->ssl_notifications->ssl_notifications_timing
                                && (
                                    !isset($row->ssl->last_notification_datetime)
                                    || ($row->ssl->last_notification_datetime && (new \DateTime($row->ssl->last_notification_datetime))->diff(new \DateTime())->days > $ssl_notification_every_x_day)
                                )
                            ) {

                                try {
                                    fire_and_forget(
                                        'GET',
                                        sprintf(
                                            'https://api.telegram.org/bot%s/sendMessage?chat_id=%s&text=%s',
                                            $notification_handler->settings->telegram,
                                            $notification_handler->settings->telegram_chat_id,
                                            sprintf(
                                                l('domain_name.simple_notification.ssl', $row->language),
                                                $row->name,
                                                $row->target,
                                                $ssl_expires_in_days,
                                                \Altum\Date::get($ssl['end_datetime']),
                                                $row->timezone,
                                                "\r\n\r\n",
                                                url('domain-name/' . $row->domain_name_id)
                                            )
                                        )
                                    );
                                } catch (\Exception $exception) {
                                    error_log($exception->getMessage());
                                }

                            }
                        }

                        break;

                    case 'microsoft_teams':

                        if(in_array($notification_handler->notification_handler_id, $row->whois_notifications->whois_notifications) && $whois) {
                            if(
                                $whois_expires_in_days
                                && (new \DateTime($whois['end_datetime'])) >= (new \DateTime())
                                && $whois_expires_in_days <= $row->whois_notifications->whois_notifications_timing
                                && (
                                    !isset($row->whois->last_notification_datetime)
                                    || ($row->whois->last_notification_datetime && (new \DateTime($row->whois->last_notification_datetime))->diff(new \DateTime())->days > $whois_notification_every_x_day)
                                )
                            ) {

                                try {
                                    \Unirest\Request::post(
                                        $notification_handler->settings->microsoft_teams,
                                        ['Content-Type' => 'application/json'],
                                        \Unirest\Request\Body::json([
                                            'text' => sprintf(
                                                l('domain_name.simple_notification.whois', $row->language),
                                                $row->name,
                                                $row->target,
                                                $whois_expires_in_days,
                                                \Altum\Date::get($whois['end_datetime']),
                                                $row->timezone,
                                                "\r\n\r\n",
                                                url('domain-name/' . $row->domain_name_id)
                                            ),
                                        ])
                                    );
                                } catch (\Exception $exception) {
                                    error_log($exception->getMessage());
                                }

                                \Unirest\Request::auth('', '');

                            }
                        }

                        if(in_array($notification_handler->notification_handler_id, $row->ssl_notifications->ssl_notifications) && $ssl) {
                            if(
                                $ssl_expires_in_days
                                && (new \DateTime($ssl['end_datetime'])) >= (new \DateTime())
                                && $ssl_expires_in_days <= $row->ssl_notifications->ssl_notifications_timing
                                && (
                                    !isset($row->ssl->last_notification_datetime)
                                    || ($row->ssl->last_notification_datetime && (new \DateTime($row->ssl->last_notification_datetime))->diff(new \DateTime())->days > $ssl_notification_every_x_day)
                                )
                            ) {

                                try {
                                    \Unirest\Request::post(
                                        $notification_handler->settings->microsoft_teams,
                                        ['Content-Type' => 'application/json'],
                                        \Unirest\Request\Body::json([
                                            'text' => sprintf(
                                                l('domain_name.simple_notification.ssl', $row->language),
                                                $row->name,
                                                $row->target,
                                                $ssl_expires_in_days,
                                                \Altum\Date::get($ssl['end_datetime']),
                                                $row->timezone,
                                                "\r\n\r\n",
                                                url('domain-name/' . $row->domain_name_id)
                                            ),
                                        ])
                                    );
                                } catch (\Exception $exception) {
                                    error_log($exception->getMessage());
                                }

                                \Unirest\Request::auth('', '');

                            }
                        }

                        break;

                    case 'x':

                        $twitter = new \Abraham\TwitterOAuth\TwitterOAuth(
                            $notification_handler->settings->x_consumer_key,
                            $notification_handler->settings->x_consumer_secret,
                            $notification_handler->settings->x_access_token,
                            $notification_handler->settings->x_access_token_secret
                        );

                        $twitter->setApiVersion('2');

                        if(in_array($notification_handler->notification_handler_id, $row->whois_notifications->whois_notifications) && $whois) {
                            if(
                                $whois_expires_in_days
                                && (new \DateTime($whois['end_datetime'])) >= (new \DateTime())
                                && $whois_expires_in_days <= $row->whois_notifications->whois_notifications_timing
                                && (
                                    !isset($row->whois->last_notification_datetime)
                                    || ($row->whois->last_notification_datetime && (new \DateTime($row->whois->last_notification_datetime))->diff(new \DateTime())->days > $whois_notification_every_x_day)
                                )
                            ) {

                                try {
                                    $response = $twitter->post('tweets', ['text' => sprintf(
                                        l('domain_name.simple_notification.whois', $row->language),
                                        $row->name,
                                        $row->target,
                                        $whois_expires_in_days,
                                        \Altum\Date::get($whois['end_datetime']),
                                        $row->timezone,
                                        "\r\n\r\n",
                                        url('domain-name/' . $row->domain_name_id)
                                    )]);
                                } catch (\Exception $exception) {
                                    /* :* */
                                }

                            }
                        }

                        if(in_array($notification_handler->notification_handler_id, $row->ssl_notifications->ssl_notifications) && $ssl) {
                            if(
                                $ssl_expires_in_days
                                && (new \DateTime($ssl['end_datetime'])) >= (new \DateTime())
                                && $ssl_expires_in_days <= $row->ssl_notifications->ssl_notifications_timing
                                && (
                                    !isset($row->ssl->last_notification_datetime)
                                    || ($row->ssl->last_notification_datetime && (new \DateTime($row->ssl->last_notification_datetime))->diff(new \DateTime())->days > $ssl_notification_every_x_day)
                                )
                            ) {

                                try {
                                    $response = $twitter->post('tweets', ['text' => sprintf(
                                        l('domain_name.simple_notification.ssl', $row->language),
                                        $row->name,
                                        $row->target,
                                        $ssl_expires_in_days,
                                        \Altum\Date::get($ssl['end_datetime']),
                                        $row->timezone,
                                        "\r\n\r\n",
                                        url('domain-name/' . $row->domain_name_id)
                                    )]);
                                } catch (\Exception $exception) {
                                    /* :* */
                                }

                            }
                        }

                        break;

                    case 'twilio':

                        if(in_array($notification_handler->notification_handler_id, $row->whois_notifications->whois_notifications) && $whois) {
                            if(
                                $whois_expires_in_days
                                && (new \DateTime($whois['end_datetime'])) >= (new \DateTime())
                                && $whois_expires_in_days <= $row->whois_notifications->whois_notifications_timing
                                && (
                                    !isset($row->whois->last_notification_datetime)
                                    || ($row->whois->last_notification_datetime && (new \DateTime($row->whois->last_notification_datetime))->diff(new \DateTime())->days > $whois_notification_every_x_day)
                                )
                            ) {

                                try {
                                    \Unirest\Request::auth(settings()->notification_handlers->twilio_sid, settings()->notification_handlers->twilio_token);

                                    \Unirest\Request::post(
                                        sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', settings()->notification_handlers->twilio_sid),
                                        [],
                                        [
                                            'From' => settings()->notification_handlers->twilio_number,
                                            'To' => $notification_handler->settings->twilio,
                                            'Body' => sprintf(
                                                l('domain_name.simple_notification.whois', $row->language),
                                                $row->name,
                                                $row->target,
                                                $whois_expires_in_days,
                                                \Altum\Date::get($whois['end_datetime']),
                                                $row->timezone,
                                                "\r\n\r\n",
                                                url('domain-name/' . $row->domain_name_id)
                                            ),
                                        ]
                                    );
                                } catch (\Exception $exception) {
                                    error_log($exception->getMessage());
                                }

                                \Unirest\Request::auth('', '');

                            }
                        }

                        if(in_array($notification_handler->notification_handler_id, $row->ssl_notifications->ssl_notifications) && $ssl) {
                            if(
                                $ssl_expires_in_days
                                && (new \DateTime($ssl['end_datetime'])) >= (new \DateTime())
                                && $ssl_expires_in_days <= $row->ssl_notifications->ssl_notifications_timing
                                && (
                                    !isset($row->ssl->last_notification_datetime)
                                    || ($row->ssl->last_notification_datetime && (new \DateTime($row->ssl->last_notification_datetime))->diff(new \DateTime())->days > $ssl_notification_every_x_day)
                                )
                            ) {

                                try {
                                    \Unirest\Request::auth(settings()->notification_handlers->twilio_sid, settings()->notification_handlers->twilio_token);

                                    \Unirest\Request::post(
                                        sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', settings()->notification_handlers->twilio_sid),
                                        [],
                                        [
                                            'From' => settings()->notification_handlers->twilio_number,
                                            'To' => $notification_handler->settings->twilio,
                                            'Body' => sprintf(
                                                l('domain_name.simple_notification.ssl', $row->language),
                                                $row->name,
                                                $row->target,
                                                $ssl_expires_in_days,
                                                \Altum\Date::get($ssl['end_datetime']),
                                                $row->timezone,
                                                "\r\n\r\n",
                                                url('domain-name/' . $row->domain_name_id)
                                            ),
                                        ]
                                    );
                                } catch (\Exception $exception) {
                                    error_log($exception->getMessage());
                                }

                                \Unirest\Request::auth('', '');

                            }
                        }

                        break;

                    case 'twilio_call':

                        if(in_array($notification_handler->notification_handler_id, $row->whois_notifications->whois_notifications) && $whois) {
                            if(
                                $whois_expires_in_days
                                && (new \DateTime($whois['end_datetime'])) >= (new \DateTime())
                                && $whois_expires_in_days <= $row->whois_notifications->whois_notifications_timing
                                && (
                                    !isset($row->whois->last_notification_datetime)
                                    || ($row->whois->last_notification_datetime && (new \DateTime($row->whois->last_notification_datetime))->diff(new \DateTime())->days > $whois_notification_every_x_day)
                                )
                            ) {

                                try {
                                    \Unirest\Request::auth(settings()->notification_handlers->twilio_sid, settings()->notification_handlers->twilio_token);

                                    \Unirest\Request::post(
                                        sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Calls.json', settings()->notification_handlers->twilio_sid),
                                        [],
                                        [
                                            'From' => settings()->notification_handlers->twilio_number,
                                            'To' => $notification_handler->settings->twilio,
                                            'Url' => SITE_URL . 'twiml/domain_name.simple_notification.whois?param1=' . urlencode($row->name) . '&param2=' . urlencode($row->target) . '&param3=' . $whois_expires_in_days . '&param4=' . \Altum\Date::get($whois['end_datetime']) . '&param5=' . $row->timezone . '&param6=&param7=' . url('domain-name/' . $row->domain_name_id),
                                        ]
                                    );
                                } catch (\Exception $exception) {
                                    error_log($exception->getMessage());
                                }

                                \Unirest\Request::auth('', '');

                            }
                        }

                        if(in_array($notification_handler->notification_handler_id, $row->ssl_notifications->ssl_notifications) && $ssl) {
                            if(
                                $ssl_expires_in_days
                                && (new \DateTime($ssl['end_datetime'])) >= (new \DateTime())
                                && $ssl_expires_in_days <= $row->ssl_notifications->ssl_notifications_timing
                                && (
                                    !isset($row->ssl->last_notification_datetime)
                                    || ($row->ssl->last_notification_datetime && (new \DateTime($row->ssl->last_notification_datetime))->diff(new \DateTime())->days > $ssl_notification_every_x_day)
                                )
                            ) {

                                try {
                                    \Unirest\Request::auth(settings()->notification_handlers->twilio_sid, settings()->notification_handlers->twilio_token);

                                    \Unirest\Request::post(
                                        sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Calls.json', settings()->notification_handlers->twilio_sid),
                                        [],
                                        [
                                            'From' => settings()->notification_handlers->twilio_number,
                                            'To' => $notification_handler->settings->twilio,
                                            'Url' => SITE_URL . 'twiml/domain_name.simple_notification.ssl?param1=' . urlencode($row->name) . '&param2=' . urlencode($row->target) . '&param3=' . $ssl_expires_in_days . '&param4=' . \Altum\Date::get($ssl['end_datetime']) . '&param5=' . $row->timezone . '&param6=&param7=' . url('domain-name/' . $row->domain_name_id),
                                        ]
                                    );
                                } catch (\Exception $exception) {
                                    error_log($exception->getMessage());
                                }

                                \Unirest\Request::auth('', '');

                            }
                        }

                        break;

                    case 'whatsapp':

                        if(in_array($notification_handler->notification_handler_id, $row->whois_notifications->whois_notifications) && $whois) {
                            if(
                                $whois_expires_in_days
                                && (new \DateTime($whois['end_datetime'])) >= (new \DateTime())
                                && $whois_expires_in_days <= $row->whois_notifications->whois_notifications_timing
                                && (
                                    !isset($row->whois->last_notification_datetime)
                                    || ($row->whois->last_notification_datetime && (new \DateTime($row->whois->last_notification_datetime))->diff(new \DateTime())->days > $whois_notification_every_x_day)
                                )
                            ) {

                                try {
                                    \Unirest\Request::post(
                                        'https://graph.facebook.com/v18.0/' . settings()->notification_handlers->whatsapp_number_id . '/messages',
                                        [
                                            'Authorization' => 'Bearer ' . settings()->notification_handlers->whatsapp_access_token,
                                            'Content-Type' => 'application/json'
                                        ],
                                        \Unirest\Request\Body::json([
                                            'messaging_product' => 'whatsapp',
                                            'to' => $notification_handler->settings->whatsapp,
                                            'type' => 'template',
                                            'template' => [
                                                'name' => 'domain_name_whois',
                                                'language' => [
                                                    'code' => \Altum\Language::$default_code
                                                ],
                                                'components' => [[
                                                    'type' => 'body',
                                                    'parameters' => [
                                                        [
                                                            'type' => 'text',
                                                            'text' => $row->name
                                                        ],
                                                        [
                                                            'type' => 'text',
                                                            'text' => $row->target
                                                        ],
                                                        [
                                                            'type' => 'text',
                                                            'text' => $whois_expires_in_days
                                                        ],
                                                        [
                                                            'type' => 'text',
                                                            'text' => \Altum\Date::get($whois['end_datetime']) . ' ' . $row->timezone
                                                        ],
                                                        [
                                                            'type' => 'text',
                                                            'text' => url('domain-name/' . $row->domain_name_id)
                                                        ],
                                                    ]
                                                ]]

                                            ]
                                        ])
                                    );
                                } catch (\Exception $exception) {
                                    error_log($exception->getMessage());
                                }

                            }
                        }

                        if(in_array($notification_handler->notification_handler_id, $row->ssl_notifications->ssl_notifications) && $ssl) {
                            if(
                                $ssl_expires_in_days
                                && (new \DateTime($ssl['end_datetime'])) >= (new \DateTime())
                                && $ssl_expires_in_days <= $row->ssl_notifications->ssl_notifications_timing
                                && (
                                    !isset($row->ssl->last_notification_datetime)
                                    || ($row->ssl->last_notification_datetime && (new \DateTime($row->ssl->last_notification_datetime))->diff(new \DateTime())->days > $ssl_notification_every_x_day)
                                )
                            ) {

                                try {
                                    \Unirest\Request::post(
                                        'https://graph.facebook.com/v18.0/' . settings()->notification_handlers->whatsapp_number_id . '/messages',
                                        [
                                            'Authorization' => 'Bearer ' . settings()->notification_handlers->whatsapp_access_token,
                                            'Content-Type' => 'application/json'
                                        ],
                                        \Unirest\Request\Body::json([
                                            'messaging_product' => 'whatsapp',
                                            'to' => $notification_handler->settings->whatsapp,
                                            'type' => 'template',
                                            'template' => [
                                                'name' => 'domain_name_whois',
                                                'language' => [
                                                    'code' => \Altum\Language::$default_code
                                                ],
                                                'components' => [[
                                                    'type' => 'body',
                                                    'parameters' => [
                                                        [
                                                            'type' => 'text',
                                                            'text' => $row->name
                                                        ],
                                                        [
                                                            'type' => 'text',
                                                            'text' => $row->target
                                                        ],
                                                        [
                                                            'type' => 'text',
                                                            'text' => $ssl_expires_in_days
                                                        ],
                                                        [
                                                            'type' => 'text',
                                                            'text' => \Altum\Date::get($ssl['end_datetime']) . ' ' . $row->timezone
                                                        ],
                                                        [
                                                            'type' => 'text',
                                                            'text' => url('domain-name/' . $row->domain_name_id)
                                                        ],
                                                    ]
                                                ]]

                                            ]
                                        ])
                                    );
                                } catch (\Exception $exception) {
                                    error_log($exception->getMessage());
                                }

                            }
                        }

                        break;

                    case 'push_subscriber_id':

                        if(in_array($notification_handler->notification_handler_id, $row->whois_notifications->whois_notifications) && $whois) {
                            if(
                                $whois_expires_in_days
                                && (new \DateTime($whois['end_datetime'])) >= (new \DateTime())
                                && $whois_expires_in_days <= $row->whois_notifications->whois_notifications_timing
                                && (
                                    !isset($row->whois->last_notification_datetime)
                                    || ($row->whois->last_notification_datetime && (new \DateTime($row->whois->last_notification_datetime))->diff(new \DateTime())->days > $whois_notification_every_x_day)
                                )
                            ) {

                                $push_subscriber = db()->where('push_subscriber_id', $notification_handler->settings->push_subscriber_id)->getOne('push_subscribers');
                                if(!$push_subscriber) {
                                    db()->where('notification_handler_id', $notification_handler->notification_handler_id)->update('notification_handlers', ['is_enabled' => 0]);
                                };

                                /* Prepare the web push */
                                $push_notification = \Altum\Helpers\PushNotifications::send([
                                    'title' => l('domain_name.push_notification.whois.title', $row->language),
                                    'description' => sprintf(l('domain_name.push_notification.description', $row->language), $row->name, $row->target, $whois_expires_in_days),
                                    'url' => url('domain-name/' . $row->domain_name_id),
                                ], $push_subscriber);

                                /* Unsubscribe if push failed */
                                if(!$push_notification) {
                                    db()->where('push_subscriber_id', $push_subscriber->push_subscriber_id)->delete('push_subscribers');
                                    db()->where('notification_handler_id', $notification_handler->notification_handler_id)->update('notification_handlers', ['is_enabled' => 0]);
                                }

                            }
                        }

                        if(in_array($notification_handler->notification_handler_id, $row->ssl_notifications->ssl_notifications) && $ssl) {
                            if(
                                $ssl_expires_in_days
                                && (new \DateTime($ssl['end_datetime'])) >= (new \DateTime())
                                && $ssl_expires_in_days <= $row->ssl_notifications->ssl_notifications_timing
                                && (
                                    !isset($row->ssl->last_notification_datetime)
                                    || ($row->ssl->last_notification_datetime && (new \DateTime($row->ssl->last_notification_datetime))->diff(new \DateTime())->days > $ssl_notification_every_x_day)
                                )
                            ) {

                                $push_subscriber = db()->where('push_subscriber_id', $notification_handler->settings->push_subscriber_id)->getOne('push_subscribers');
                                if(!$push_subscriber) {
                                    db()->where('notification_handler_id', $notification_handler->notification_handler_id)->update('notification_handlers', ['is_enabled' => 0]);
                                };

                                /* Prepare the web push */
                                $push_notification = \Altum\Helpers\PushNotifications::send([
                                    'title' => l('domain_name.push_notification.ssl.title', $row->language),
                                    'description' => sprintf(l('domain_name.push_notification.description', $row->language), $row->name, $row->target, $ssl_expires_in_days),
                                    'url' => url('domain-name/' . $row->domain_name_id),
                                ], $push_subscriber);

                                /* Unsubscribe if push failed */
                                if(!$push_notification) {
                                    db()->where('push_subscriber_id', $push_subscriber->push_subscriber_id)->delete('push_subscribers');
                                    db()->where('notification_handler_id', $notification_handler->notification_handler_id)->update('notification_handlers', ['is_enabled' => 0]);
                                }

                            }
                        }

                        break;
                }
            }

            if(
                $whois_expires_in_days
                && (new \DateTime($whois['end_datetime'])) >= (new \DateTime())
                && $whois_expires_in_days <= $row->whois_notifications->whois_notifications_timing
                && (
                    !isset($row->whois->last_notification_datetime)
                    || ($row->whois->last_notification_datetime && (new \DateTime($row->whois->last_notification_datetime))->diff(new \DateTime())->days > $whois_notification_every_x_day)
                )
            ) {
                $whois['last_notification_datetime'] = get_date();
            }

            if(
                $ssl_expires_in_days
                && (new \DateTime($ssl['end_datetime'])) >= (new \DateTime())
                && $ssl_expires_in_days <= $row->ssl_notifications->ssl_notifications_timing
                && (
                    !isset($row->ssl->last_notification_datetime)
                    || ($row->ssl->last_notification_datetime && (new \DateTime($row->ssl->last_notification_datetime))->diff(new \DateTime())->days > $ssl_notification_every_x_day)
                )
            ) {
                $ssl['last_notification_datetime'] = get_date();
            }

            $whois = json_encode(empty($whois) ? (object) [] : $whois);
            $ssl = json_encode(empty($ssl) ? (object) [] : $ssl);

            /* Update the domain name */
            db()->where('domain_name_id', $row->domain_name_id)->update('domain_names', [
                'whois' => $whois,
                'ssl' => $ssl,
                'total_checks' => db()->inc(),
                'last_check_datetime' => get_date(),
                'next_check_datetime' => (new \DateTime())->modify('+1 day')->format('Y-m-d H:i:s'),
            ]);

            /* Clear the cache */
            cache()->deleteItemsByTag('domain_name_id=' . $row->domain_name_id);

        }

        $this->close();
    }

    public function dns_monitors() {

        if(!settings()->monitors_heartbeats->dns_monitors_is_enabled) {
            return;
        }

        $this->initiate();

        $date = get_date();

        /* Update cron job last run date */
        $this->update_cron_execution_datetimes('dns_monitors_datetime');

        for($i = 1; $i <= 1000; $i++) {
            $row = database()->query("
                SELECT
                    `dns_monitors`.*,
                    `users`.`email`,
                    `users`.`plan_settings`,
                    `users`.`language`,
                    `users`.`timezone`,
                    `users`.`anti_phishing_code`
                FROM 
                    `dns_monitors`
                LEFT JOIN 
                    `users` ON `dns_monitors`.`user_id` = `users`.`user_id` 
                WHERE 
                    `dns_monitors`.`is_enabled` = 1
                    AND `dns_monitors`.`next_check_datetime` <= '{$date}' 
                    AND `users`.`status` = 1
                ORDER BY `dns_monitors`.`next_check_datetime`
                LIMIT 1
            ")->fetch_object();

            /* Break if no results */
            if(!$row) break;

            if(DEBUG) printf('Going through %s (%s) dns monitor..<br />', $row->name, $row->target);

            $row->plan_settings = json_decode($row->plan_settings ?? '');
            $row->notifications = json_decode($row->notifications ?? '');
            $row->settings = json_decode($row->settings ?? '');
            $row->dns = json_decode($row->dns ?? '');

            /* Get available notification handlers */
            $notification_handlers = (new \Altum\Models\NotificationHandlers())->get_notification_handlers_by_user_id($row->user_id);

            /* DNS Check */
            $dns = [];
            $dns_changes = [];
            $text_notification_content = [];
            $total_dns_types_found = 0;
            $total_dns_records_found = 0;

            $dns_types = require APP_PATH . 'includes/dns_monitor_types.php';

            /* Get and process all DNS types */
            foreach($row->settings->dns_types as $dns_type) {
                $dns_records = @dns_get_record($row->target . '.', $dns_types[$dns_type]);
                $dns[$dns_type] = [];

                if($dns_records) {
                    foreach($dns_records as $dns_record) {
                        unset($dns_record['class']);
                        unset($dns_record['ttl']);
                        unset($dns_record['type']);
                        unset($dns_record['entries']);

                        $dns[$dns_type][] = $dns_record;

                        /* Add distinct keys for sorting */
                        switch($dns_type) {
                            CASE 'SOA':
                                foreach($dns[$dns_type] as $key => $value) {
                                    $dns[$dns_type][$key]['id'] = md5($value['mname'] . $value['rname'] . $value['serial'] . $value['refresh'] . $value['retry'] . $value['expire'] . $value['minimum-ttl']);
                                }
                                break;

                            CASE 'CAA':
                                foreach($dns[$dns_type] as $key => $value) {
                                    $dns[$dns_type][$key]['id'] = md5($value['flags'] . $value['tag'] . $value['value']);
                                }
                                break;

                            CASE 'MX':
                                foreach($dns[$dns_type] as $key => $value) {
                                    $dns[$dns_type][$key]['id'] = md5($value['target'] . $value['pri']);
                                }
                                break;
                        }
                    }


                    /* Ordering */
                    switch($dns_type) {
                        case 'A':
                            usort($dns[$dns_type], function ($a, $b) {
                                return strcmp($a['ip'], $b['ip']);
                            });
                            break;

                        case 'NS':
                        case 'CNAME':
                            usort($dns[$dns_type], function ($a, $b) {
                                return strcmp($a['target'], $b['target']);
                            });
                            break;

                        case 'TXT':
                            usort($dns[$dns_type], function ($a, $b) {
                                return strcmp($a['txt'], $b['txt']);
                            });
                            break;

                        case 'AAAA':
                            usort($dns[$dns_type], function ($a, $b) {
                                return strcmp($a['ipv6'], $b['ipv6']);
                            });
                            break;

                        case 'SOA':
                        case 'MX':
                        case 'CAA':
                            usort($dns[$dns_type], function ($a, $b) {
                                return strcmp($a['id'], $b['id']);
                            });
                            break;
                    }
                }

                $total_dns_types_found += count($dns[$dns_type]) ? 1 : 0;
                $total_dns_records_found += count($dns[$dns_type]);
            }

            /* Potential checks against the previous DNS records */
            if($row->dns && $row->total_checks > 0) {
                foreach($row->settings->dns_types as $dns_type) {
                    $old_count = count($row->dns->{$dns_type} ?? []);
                    $new_count = count($dns[$dns_type]);
                    $total_count = max($old_count, $new_count);

                    /* Go over each of the old dns record type */
                    for($i = 0; $i < $total_count; $i++) {
                        /* Check if old value exists */
                        if(!isset($row->dns->{$dns_type}[$i])) {
                            $dns_changes[] = [
                                'dns_type' => $dns_type,
                                'type' => 'added',
                                'old' => [],
                                'new' => $dns[$dns_type][$i],
                            ];

                            if(in_array($dns_type, ['SOA', 'CAA', 'MX'])) {
                                unset($dns[$dns_type][$i]['id']);
                            }

                            $array_of_dns_values = array_diff_key(array_values($dns[$dns_type][$i]), array_flip(['id']));

                            $text_notification_content[] = l('dns_monitor.added') . ': ' . $dns_type . ' ' . implode(' ', $array_of_dns_values);
                        }

                        /* Check if new value exists */
                        if(!isset($dns[$dns_type][$i])) {
                            $dns_changes[] = [
                                'dns_type' => $dns_type,
                                'type' => 'removed',
                                'old' => $row->dns->{$dns_type}[$i],
                                'new' => [],
                            ];

                            $array_of_dns_values = array_diff_key(array_values((array) $row->dns->{$dns_type}[$i]), array_flip(['id']));

                            $text_notification_content[] = l('dns_monitor.removed') . ': ' . $dns_type . ' ' . implode(' ', $array_of_dns_values);
                        }

                        /* Checks based on the type of the DNS */
                        if(isset($row->dns->{$dns_type}[$i]) && isset($dns[$dns_type][$i])) {
                            $changed = null;

                            switch($dns_type) {
                                case 'A':

                                    if($row->dns->{$dns_type}[$i]->ip !== $dns[$dns_type][$i]['ip']) {
                                        $changed = true;
                                    }

                                    break;

                                case 'CAA':

                                    if($row->dns->{$dns_type}[$i]->tag !== $dns[$dns_type][$i]['tag']) {
                                        $changed = true;
                                    }

                                    if($row->dns->{$dns_type}[$i]->value !== $dns[$dns_type][$i]['value']) {
                                        $changed = true;
                                    }

                                    if($row->dns->{$dns_type}[$i]->flags !== $dns[$dns_type][$i]['flags']) {
                                        $changed = true;
                                    }

                                    break;

                                case 'MX':

                                    if($row->dns->{$dns_type}[$i]->target !== $dns[$dns_type][$i]['target']) {
                                        $changed = true;
                                    }

                                    if($row->dns->{$dns_type}[$i]->pri !== $dns[$dns_type][$i]['pri']) {
                                        $changed = true;
                                    }

                                    break;

                                case 'CNAME':
                                case 'NS':

                                    if($row->dns->{$dns_type}[$i]->target !== $dns[$dns_type][$i]['target']) {
                                        $changed = true;
                                    }

                                    break;

                                case 'TXT':

                                    if($row->dns->{$dns_type}[$i]->txt !== $dns[$dns_type][$i]['txt']) {
                                        $changed = true;
                                    }

                                    break;

                                case 'SOA':

                                    if($row->dns->{$dns_type}[$i]->id !== $dns[$dns_type][$i]['id']) {
                                        $changed = true;
                                    }

                                    break;

                                case 'AAAA':

                                    if($row->dns->{$dns_type}[$i]->ipv6 !== $dns[$dns_type][$i]['ipv6']) {
                                        $changed = true;
                                    }

                                    break;
                            }

                            if($changed) {
                                $dns_changes[] = [
                                    'dns_type' => $dns_type,
                                    'type' => 'changed',
                                    'old' => $row->dns->{$dns_type}[$i],
                                    'new' => $dns[$dns_type][$i],
                                ];

                                $array_of_dns_values_old = array_diff_key((array)$row->dns->{$dns_type}[$i], array_flip(['id']));
                                $array_of_dns_values_new = array_diff_key(array_values((array)$dns[$dns_type][$i]), array_flip(['id']));

                                $text_notification_content[] =
                                    l('dns_monitor.changed') . ' (' . l('dns_monitor.old') . '): ' . $dns_type . ' ' . implode(' ', array_values($array_of_dns_values_old))
                                    . "{LINEBREAK}"
                                    . l('dns_monitor.changed') . ' (' . l('dns_monitor.new') . '): ' . $dns_type . ' ' . implode(' ', array_values($array_of_dns_values_new));

                            }
                        }
                    }
                }
            }

            /* Get the language for the user and set the timezone */
            \Altum\Date::$timezone = $row->timezone;

            /* Only send notifications if DNS has changed */
            $dns_has_changed = count($dns_changes);

            if($dns_has_changed) {
                foreach($notification_handlers as $notification_handler) {
                    if(!$notification_handler->is_enabled) continue;
                    if(!in_array($notification_handler->notification_handler_id, $row->notifications)) continue;

                    switch ($notification_handler->type) {
                        case 'email':

                            /* Prepare the email title */
                            $email_title = sprintf(l('cron.dns_monitor.title', $row->language), $row->name);

                            /* Prepare the View for the email content */
                            $data = [
                                'row' => $row,
                                'dns_changes' => $dns_changes,
                                'content' => implode("<br /><br />", str_replace('{LINEBREAK}', "<br />", $text_notification_content)),
                            ];

                            $email_content = (new \Altum\View('partials/cron/dns_monitor', (array) $this))->run($data);

                            /* Send the email */
                            send_mail($notification_handler->settings->email, $email_title, $email_content, ['anti_phishing_code' => $row->anti_phishing_code, 'language' => $row->language]);

                            break;

                        case 'webhook':

                            fire_and_forget('post', $notification_handler->settings->webhook, [
                                'dns_monitor_id' => $row->dns_monitor_id,
                                'name' => $row->name,
                                'dns_changes_json' => json_encode($dns_changes),
                                'url' => url('dns-monitor/' . $row->dns_monitor_id),
                            ]);

                            break;

                        case 'slack':

                            try {
                                \Unirest\Request::post(
                                    $notification_handler->settings->slack,
                                    ['Accept' => 'application/json'],
                                    \Unirest\Request\Body::json([
                                        'text' => sprintf(
                                            l('dns_monitor.simple_notification', $row->language),
                                            $row->name,
                                            $row->target,
                                            "\r\n\r\n" . implode("\r\n\r\n", str_replace('{LINEBREAK}', "\r\n", $text_notification_content)) . "\r\n\r\n",
                                            url('dns-monitor/' . $row->dns_monitor_id)
                                        ),
                                        'username' => settings()->main->title,
                                        'icon_emoji' => ':large_red_square:'
                                    ])
                                );
                            } catch (\Exception $exception) {
                                error_log($exception->getMessage());
                            }

                            break;

                        case 'discord':

                            try {
                                fire_and_forget(
                                    'POST',
                                    $notification_handler->settings->discord,
                                    [
                                        'content' => sprintf(
                                            l('dns_monitor.simple_notification', $row->language),
                                            $row->name,
                                            $row->target,
                                            "\r\n\r\n" . implode("\r\n\r\n", str_replace('{LINEBREAK}', "\r\n", $text_notification_content)) . "\r\n\r\n",
                                            url('dns-monitor/' . $row->dns_monitor_id)
                                        )
                                    ],
                                    'json',
                                    [
                                        'Accept' => 'application/json',
                                        'Content-Type' => 'application/json',
                                    ],
                                );
                            } catch (\Exception $exception) {
                                error_log($exception->getMessage());
                            }

                            break;

                        case 'telegram':

                            try {
                                fire_and_forget(
                                    'GET',
                                    sprintf(
                                        'https://api.telegram.org/bot%s/sendMessage?chat_id=%s&text=%s',
                                        $notification_handler->settings->telegram,
                                        $notification_handler->settings->telegram_chat_id,
                                        sprintf(
                                            l('dns_monitor.simple_notification', $row->language),
                                            $row->name,
                                            $row->target,
                                            urlencode("\r\n\r\n") . implode(urlencode("\r\n\r\n"), str_replace('{LINEBREAK}', urlencode("\r\n\r\n"), $text_notification_content)) . urlencode("\r\n\r\n"),
                                            url('dns-monitor/' . $row->dns_monitor_id)
                                        )
                                    )
                                );
                            } catch (\Exception $exception) {
                                error_log($exception->getMessage());
                            }

                            break;

                        case 'microsoft_teams':

                            try {
                                \Unirest\Request::post(
                                    $notification_handler->settings->microsoft_teams,
                                    ['Content-Type' => 'application/json'],
                                    \Unirest\Request\Body::json([
                                        'text' => sprintf(
                                            l('dns_monitor.simple_notification', $row->language),
                                            $row->name,
                                            $row->target,
                                            "\r\n\r\n" . implode("\r\n\r\n", str_replace('{LINEBREAK}', "\r\n", $text_notification_content)) . "\r\n\r\n",
                                            url('dns-monitor/' . $row->dns_monitor_id)
                                        ),
                                    ])
                                );
                            } catch (\Exception $exception) {
                                error_log($exception->getMessage());
                            }

                            break;

                        case 'x':

                            $twitter = new \Abraham\TwitterOAuth\TwitterOAuth(
                                $notification_handler->settings->x_consumer_key,
                                $notification_handler->settings->x_consumer_secret,
                                $notification_handler->settings->x_access_token,
                                $notification_handler->settings->x_access_token_secret
                            );

                            $twitter->setApiVersion('2');

                            try {
                                $response = $twitter->post('tweets', ['text' => sprintf(
                                    l('dns_monitor.simple_notification', $row->language),
                                    $row->name,
                                    $row->target,
                                    "\r\n\r\n" . implode("\r\n\r\n", str_replace('{LINEBREAK}', "\r\n", $text_notification_content)) . "\r\n\r\n",
                                    url('dns-monitor/' . $row->dns_monitor_id)
                                )]);
                            } catch (\Exception $exception) {
                                /* :* */
                            }

                            break;

                        case 'twilio':

                            try {
                                \Unirest\Request::auth(settings()->notification_handlers->twilio_sid, settings()->notification_handlers->twilio_token);

                                \Unirest\Request::post(
                                    sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', settings()->notification_handlers->twilio_sid),
                                    [],
                                    [
                                        'From' => settings()->notification_handlers->twilio_number,
                                        'To' => $notification_handler->settings->twilio,
                                        'Body' => sprintf(
                                            l('dns_monitor.simple_notification', $row->language),
                                            $row->name,
                                            $row->target,
                                            "\r\n\r\n" . implode("\r\n\r\n", str_replace('{LINEBREAK}', "\r\n", $text_notification_content)) . "\r\n\r\n",
                                            url('dns-monitor/' . $row->dns_monitor_id)
                                        ),
                                    ]
                                );
                            } catch (\Exception $exception) {
                                error_log($exception->getMessage());
                            }

                            \Unirest\Request::auth('', '');

                            break;

                        case 'twilio_call':

                            try {
                                \Unirest\Request::auth(settings()->notification_handlers->twilio_sid, settings()->notification_handlers->twilio_token);

                                \Unirest\Request::post(
                                    sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Calls.json', settings()->notification_handlers->twilio_sid),
                                    [],
                                    [
                                        'From' => settings()->notification_handlers->twilio_number,
                                        'To' => $notification_handler->settings->twilio_call,
                                        'Url' => SITE_URL . 'twiml/dns_monitor.simple_notification?param1=' . urlencode($row->name) . '&param2=' . urlencode($row->target) . '&param3=' . urlencode("\r\n\r\n" . implode("\r\n\r\n", str_replace('{LINEBREAK}', "\r\n", $text_notification_content)) . "\r\n\r\n") . '&param4=' . urlencode(url('dns-monitor/' . $row->dns_monitor_id)),
                                    ]
                                );
                            } catch (\Exception $exception) {
                                error_log($exception->getMessage());
                            }

                            \Unirest\Request::auth('', '');

                            break;


                        case 'whatsapp':

                            try {
                                \Unirest\Request::post(
                                    'https://graph.facebook.com/v18.0/' . settings()->notification_handlers->whatsapp_number_id . '/messages',
                                    [
                                        'Authorization' => 'Bearer ' . settings()->notification_handlers->whatsapp_access_token,
                                        'Content-Type' => 'application/json'
                                    ],
                                    \Unirest\Request\Body::json([
                                        'messaging_product' => 'whatsapp',
                                        'to' => $notification_handler->settings->whatsapp,
                                        'type' => 'template',
                                        'template' => [
                                            'name' => 'dns_monitor',
                                            'language' => [
                                                'code' => \Altum\Language::$default_code
                                            ],
                                            'components' => [[
                                                'type' => 'body',
                                                'parameters' => [
                                                    [
                                                        'type' => 'text',
                                                        'text' => $row->name
                                                    ],
                                                    [
                                                        'type' => 'text',
                                                        'text' => $row->target
                                                    ],
                                                    [
                                                        'type' => 'text',
                                                        'text' => url('dns-monitor/' . $row->dns_monitor_id)
                                                    ],
                                                ]
                                            ]]

                                        ]
                                    ])
                                );
                            } catch (\Exception $exception) {
                                error_log($exception->getMessage());
                            }

                            break;

                        case 'push_subscriber_id':
                            $push_subscriber = db()->where('push_subscriber_id', $notification_handler->settings->push_subscriber_id)->getOne('push_subscribers');
                            if(!$push_subscriber) {
                                db()->where('notification_handler_id', $notification_handler->notification_handler_id)->update('notification_handlers', ['is_enabled' => 0]);
                            };

                            /* Prepare the web push */
                            $push_notification = \Altum\Helpers\PushNotifications::send([
                                'title' => l('dns_monitor.push_notification.title', $row->language),
                                'description' => sprintf(l('dns_monitor.push_notification.description', $row->language), $row->name, $row->target),
                                'url' => url('dns-monitor/' . $row->dns_monitor_id),
                            ], $push_subscriber);

                            /* Unsubscribe if push failed */
                            if(!$push_notification) {
                                db()->where('push_subscriber_id', $push_subscriber->push_subscriber_id)->delete('push_subscribers');
                                db()->where('notification_handler_id', $notification_handler->notification_handler_id)->update('notification_handlers', ['is_enabled' => 0]);
                            }

                            break;

                    }
                }
            }

            $dns = json_encode($dns);
            $dns_changes = json_encode($dns_changes);

            /* Insert the DNS monitor log */
            if($dns_has_changed) {
                $dns_monitor_log_id = db()->insert('dns_monitors_logs', [
                    'dns_monitor_id' => $row->dns_monitor_id,
                    'user_id' => $row->user_id,
                    'dns' => $dns,
                    'dns_changes' => $dns_changes,
                    'total_dns_types_found' => $total_dns_types_found,
                    'total_dns_records_found' => $total_dns_records_found,
                    'datetime' => get_date(),
                ]);
            }

            /* Calculate expected next run */
            $next_check_datetime = (new \DateTime())->modify('+' . $row->settings->dns_check_interval_seconds . ' seconds')->format('Y-m-d H:i:s');
            //$next_check_datetime = (new \DateTime())->modify('+30 seconds')->format('Y-m-d H:i:s');

            /* Update the DNS monitor */
            db()->where('dns_monitor_id', $row->dns_monitor_id)->update('dns_monitors', [
                'dns' => $dns,
                'total_checks' => db()->inc(),
                'total_changes' => $dns_has_changed ? db()->inc() : $row->total_changes,
                'total_dns_types_found' => $total_dns_types_found,
                'total_dns_records_found' => $total_dns_records_found,
                'last_check_datetime' => get_date(),
                'last_change_datetime' => $dns_has_changed ? get_date() : $row->last_change_datetime,
                'next_check_datetime' => $next_check_datetime,
            ]);

            /* Clear out old dns monitor logs */
            if($row->plan_settings->logs_retention != -1) {
                $x_days_ago_datetime = (new \DateTime())->modify('-' . ($row->plan_settings->logs_retention ?? 90) . ' days')->format('Y-m-d H:i:s');
                database()->query("DELETE FROM `dns_monitors_logs` WHERE `datetime` < '{$x_days_ago_datetime}' AND `user_id` = {$row->user_id}");
            }

            /* Clear the cache */
            cache()->deleteItemsByTag('dns_monitor_id=' . $row->dns_monitor_id);

        }

        $this->close();
    }

    public function monitors_email_reports() {

        $this->initiate();

        $date = get_date();

        /* Update cron job last run date */
        $this->update_cron_execution_datetimes('monitors_email_reports_datetime');

        /* Only run this part if the email reports are enabled */
        if(!settings()->monitors_heartbeats->email_reports_is_enabled) {
            return;
        }

        /* Determine the frequency of email reports */
        $days_interval = 7;

        switch(settings()->monitors_heartbeats->email_reports_is_enabled) {
            case 'weekly':
                $days_interval = 7;

                break;

            case 'monthly':
                $days_interval = 30;

                break;
        }

        /* Get potential monitors from users that have almost all the conditions to get an email report right now */
        $result = database()->query("
            SELECT
                `monitors`.`monitor_id`,
                `monitors`.`name`,
                `monitors`.`target`,
                `monitors`.`port`,
                `monitors`.`email_reports_last_datetime`,
                `users`.`user_id`,
                `users`.`email`,
                `users`.`plan_settings`,
                `users`.`language`
            FROM 
                `monitors`
            LEFT JOIN 
                `users` ON `monitors`.`user_id` = `users`.`user_id` 
            WHERE 
                `users`.`status` = 1
                AND `monitors`.`is_enabled` = 1 
                AND `monitors`.`email_reports_is_enabled` = 1
				AND DATE_ADD(`monitors`.`email_reports_last_datetime`, INTERVAL {$days_interval} DAY) <= '{$date}'
            LIMIT 25
        ");

        /* Go through each result */
        while($row = $result->fetch_object()) {
            $row->plan_settings = json_decode($row->plan_settings);

            /* Make sure the plan still lets the user get email reports */
            if(!$row->plan_settings->email_reports_is_enabled) {
                db()->where('monitor_id', $row->monitor_id)->update('monitors', ['email_reports_is_enabled' => 0]);
                continue;
            }

            /* Prepare */
            $start_date = (new \DateTime())->modify('-' . $days_interval . ' days')->format('Y-m-d H:i:s');

            /* Monitor logs */
            $monitor_logs = [];

            $monitor_logs_result = database()->query("
                SELECT 
                    `is_ok`,
                    `response_time`,
                    `datetime`
                FROM 
                    `monitors_logs`
                WHERE 
                    `monitor_id` = {$row->monitor_id} 
                    AND (`datetime` BETWEEN '{$start_date}' AND '{$date}')
            ");

            $total_ok_checks = 0;
            $total_not_ok_checks = 0;
            $total_response_time = 0;

            while($monitor_log = $monitor_logs_result->fetch_object()) {
                $monitor_logs[] = $monitor_log;

                $total_ok_checks = $monitor_log->is_ok ? $total_ok_checks + 1 : $total_ok_checks;
                $total_not_ok_checks = !$monitor_log->is_ok ? $total_not_ok_checks + 1 : $total_not_ok_checks;
                $total_response_time += $monitor_log->response_time;
            }

            /* Monitor incidents */
            $monitor_incidents = [];

            $monitor_incidents_result = database()->query("
                SELECT 
                    `start_datetime`,
                    `end_datetime`
                FROM 
                    `incidents`
                WHERE 
                    `monitor_id` = {$row->monitor_id} 
                    AND `start_datetime` >= '{$start_date}' 
                    AND `end_datetime` <= '{$date}'
            ");

            while($monitor_incident = $monitor_incidents_result->fetch_object()) {
                $monitor_incidents[] = $monitor_incident;
            }

            /* calculate some data */
            $total_monitor_logs = count($monitor_logs);
            $uptime = $total_ok_checks > 0 ? $total_ok_checks / ($total_ok_checks + $total_not_ok_checks) * 100 : 0;
            $downtime = 100 - $uptime;
            $average_response_time = $total_ok_checks > 0 ? $total_response_time / $total_ok_checks : 0;

            /* Prepare the email title */
            $replacers = [
                '{{MONITOR:NAME}}' => $row->name,
                '{{START_DATE}}' => \Altum\Date::get($start_date, 5),
                '{{END_DATE}}' => \Altum\Date::get('', 5),
            ];

            $email_title = str_replace(
                array_keys($replacers),
                array_values($replacers),
                l('cron.monitor_email_report.title', $row->language)
            );

            /* Prepare the View for the email content */
            $data = [
                'row'                       => $row,
                'monitor_logs'              => $monitor_logs,
                'total_monitor_logs'        => $total_monitor_logs,
                'monitor_logs_data' => [
                    'uptime'                => $uptime,
                    'downtime'              => $downtime,
                    'average_response_time' => $average_response_time,
                    'total_ok_checks'       => $total_ok_checks,
                    'total_not_ok_checks'   => $total_not_ok_checks
                ],
                'monitor_incidents'         => $monitor_incidents,

                'start_date'                => $start_date,
                'end_date'                  => $date
            ];

            $email_content = (new \Altum\View('partials/cron/monitor_email_report', (array) $this))->run($data);

            /* Send the email */
            send_mail($row->email, $email_title, $email_content);

            /* Update the store */
            db()->where('monitor_id', $row->monitor_id)->update('monitors', ['email_reports_last_datetime' => $date]);

            /* Insert email log */
            db()->insert('email_reports', ['user_id' => $row->user_id, 'monitor_id' => $row->monitor_id, 'datetime' => $date]);

            if(DEBUG) {
                echo sprintf('Email sent for user_id %s and monitor_id %s', $row->user_id, $row->monitor_id);
            }
        }

        $this->close();
    }

    public function heartbeats_email_reports() {

        $this->initiate();

        $date = get_date();

        /* Update cron job last run date */
        $this->update_cron_execution_datetimes('heartbeats_email_reports_datetime');

        /* Only run this part if the email reports are enabled */
        if(!settings()->monitors_heartbeats->email_reports_is_enabled) {
            return;
        }

        /* Determine the frequency of email reports */
        $days_interval = 7;

        switch(settings()->monitors_heartbeats->email_reports_is_enabled) {
            case 'weekly':
                $days_interval = 7;

                break;

            case 'monthly':
                $days_interval = 30;

                break;
        }

        /* Get potential heartbeats from users that have almost all the conditions to get an email report right now */
        $result = database()->query("
            SELECT
                `heartbeats`.`heartbeat_id`,
                `heartbeats`.`name`,
                `heartbeats`.`email_reports_last_datetime`,
                `users`.`user_id`,
                `users`.`email`,
                `users`.`plan_settings`,
                `users`.`language`
            FROM 
                `heartbeats`
            LEFT JOIN 
                `users` ON `heartbeats`.`user_id` = `users`.`user_id` 
            WHERE 
                `users`.`status` = 1
                AND `heartbeats`.`is_enabled` = 1 
                AND `heartbeats`.`email_reports_is_enabled` = 1
				AND DATE_ADD(`heartbeats`.`email_reports_last_datetime`, INTERVAL {$days_interval} DAY) <= '{$date}'
            LIMIT 25
        ");

        /* Go through each result */
        while($row = $result->fetch_object()) {
            $row->plan_settings = json_decode($row->plan_settings);

            /* Make sure the plan still lets the user get email reports */
            if(!$row->plan_settings->email_reports_is_enabled) {
                db()->where('heartbeat_id', $row->heartbeat_id)->update('heartbeats', ['email_reports_is_enabled' => 0]);
                continue;
            }

            /* Prepare */
            $start_date = (new \DateTime())->modify('-' . $days_interval . ' days')->format('Y-m-d H:i:s');

            /* Monitor logs */
            $heartbeat_logs = [];

            $heartbeat_logs_result = database()->query("
                SELECT 
                    `is_ok`,
                    `datetime`
                FROM 
                    `heartbeats_logs`
                WHERE 
                    `heartbeat_id` = {$row->heartbeat_id} 
                    AND (`datetime` BETWEEN '{$start_date}' AND '{$date}')
            ");

            $total_runs = 0;
            $total_missed_runs = 0;

            while($heartbeat_log = $heartbeat_logs_result->fetch_object()) {
                $heartbeat_logs[] = $heartbeat_log;

                $total_runs = $heartbeat_log->is_ok ? $total_runs + 1 : $total_runs;
                $total_missed_runs = !$heartbeat_log->is_ok ? $total_missed_runs + 1 : $total_missed_runs;
            }

            /* Monitor incidents */
            $heartbeat_incidents = [];

            $heartbeat_incidents_result = database()->query("
                SELECT 
                    `start_datetime`,
                    `end_datetime`
                FROM 
                    `incidents`
                WHERE 
                    `heartbeat_id` = {$row->heartbeat_id} 
                    AND `start_datetime` >= '{$start_date}' 
                    AND `end_datetime` <= '{$date}'
            ");

            while($heartbeat_incident = $heartbeat_incidents_result->fetch_object()) {
                $heartbeat_incidents[] = $heartbeat_incident;
            }

            /* calculate some data */
            $total_heartbeat_logs = count($heartbeat_logs);
            $uptime = $total_runs > 0 ? $total_runs / ($total_runs + $total_missed_runs) * 100 : 0;
            $downtime = 100 - $uptime;

            /* Prepare the email title */
            $replacers = [
                '{{HEARTBEAT:NAME}}' => $row->name,
                '{{START_DATE}}' => \Altum\Date::get($start_date, 5),
                '{{END_DATE}}' => \Altum\Date::get('', 5),
            ];

            $email_title = str_replace(
                array_keys($replacers),
                array_values($replacers),
                l('cron.heartbeat_email_report.title', $row->language)
            );

            /* Prepare the View for the email content */
            $data = [
                'row'                       => $row,
                'heartbeat_logs'            => $heartbeat_logs,
                'total_heartbeat_logs'      => $total_heartbeat_logs,
                'heartbeat_logs_data' => [
                    'uptime'                => $uptime,
                    'downtime'              => $downtime,
                    'total_runs'            => $total_runs,
                    'total_missed_runs'     => $total_missed_runs
                ],
                'heartbeat_incidents'       => $heartbeat_incidents,

                'start_date'                => $start_date,
                'end_date'                  => $date
            ];

            $email_content = (new \Altum\View('partials/cron/heartbeat_email_report', (array) $this))->run($data);

            /* Send the email */
            send_mail($row->email, $email_title, $email_content);

            /* Update the store */
            db()->where('heartbeat_id', $row->heartbeat_id)->update('heartbeats', ['email_reports_last_datetime' => $date]);

            /* Insert email log */
            db()->insert('email_reports', ['user_id' => $row->user_id, 'heartbeat_id' => $row->heartbeat_id, 'datetime' => $date]);

            if(DEBUG) {
                echo sprintf('Email sent for user_id %s and heartbeat_id %s', $row->user_id, $row->heartbeat_id);
            }
        }

        $this->close();
    }

    public function broadcasts() {

        $this->initiate();
        $this->update_cron_execution_datetimes('broadcasts_datetime');

        /* We'll send up to 40 emails per run */
        $max_batch_size = 40;

        /* Fetch a broadcast in "processing" status */
        $broadcast = db()->where('status', 'processing')->getOne('broadcasts');
        if(!$broadcast) {
            $this->close();
            return;
        }

        $broadcast->users_ids = json_decode($broadcast->users_ids ?? '[]', true);
        $broadcast->sent_users_ids = json_decode($broadcast->sent_users_ids ?? '[]', true);
        $broadcast->settings = json_decode($broadcast->settings ?? '[]');

        /* Find which users are left to process */
        $remaining_user_ids = array_diff($broadcast->users_ids, $broadcast->sent_users_ids);

        /* If no one is left, mark broadcast as "sent" */
        if(empty($remaining_user_ids)) {
            db()->where('broadcast_id', $broadcast->broadcast_id)->update('broadcasts', [
                'status' => 'sent'
            ]);
            $this->close();
            return;
        }

        /* Get all batch users at once in one go */
        $user_ids_for_this_run = array_slice($remaining_user_ids, 0, $max_batch_size);

        $users = db()
            ->where('user_id', $user_ids_for_this_run, 'IN')
            ->get('users', null, [
                'user_id',
                'name',
                'email',
                'language',
                'anti_phishing_code',
                'continent_code',
                'country',
                'city_name',
                'device_type',
                'os_name',
                'browser_name',
                'browser_language'
            ]);

        /* Initialize PHPMailer once for this batch */
        $mail = new \PHPMailer\PHPMailer\PHPMailer();
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->isHTML(true);

        /* SMTP connection settings */
        $mail->SMTPAuth = settings()->smtp->auth;
        $mail->Host = settings()->smtp->host;
        $mail->Port = settings()->smtp->port;
        $mail->Username = settings()->smtp->username;
        $mail->Password = settings()->smtp->password;

        if(settings()->smtp->encryption != '0') {
            $mail->SMTPSecure = settings()->smtp->encryption;
        }

        /* Keep the SMTP connection alive */
        $mail->SMTPKeepAlive = true;

        /* Set From / Reply-to */
        $mail->setFrom(settings()->smtp->from, settings()->smtp->from_name);
        if(!empty(settings()->smtp->reply_to) && !empty(settings()->smtp->reply_to_name)) {
            $mail->addReplyTo(settings()->smtp->reply_to, settings()->smtp->reply_to_name);
        } else {
            $mail->addReplyTo(settings()->smtp->from, settings()->smtp->from_name);
        }

        /* Optional CC/BCC */
        if(settings()->smtp->cc) {
            foreach (explode(',', settings()->smtp->cc) as $cc_email) {
                $mail->addCC(trim($cc_email));
            }
        }
        if(settings()->smtp->bcc) {
            foreach (explode(',', settings()->smtp->bcc) as $bcc_email) {
                $mail->addBCC(trim($bcc_email));
            }
        }

        $newly_sent_user_ids = [];

        /* Loop through users and send */
        foreach ($users as $user) {

            /* Prepare placeholders and the final template */
            $vars = [
                '{{USER:NAME}}'              => $user->name,
                '{{USER:EMAIL}}'             => $user->email,
                '{{USER:CONTINENT_NAME}}'    => get_continent_from_continent_code($user->continent_code),
                '{{USER:COUNTRY_NAME}}'      => get_country_from_country_code($user->country),
                '{{USER:CITY_NAME}}'         => $user->city_name,
                '{{USER:DEVICE_TYPE}}'       => l('global.device.' . $user->device_type),
                '{{USER:OS_NAME}}'           => $user->os_name,
                '{{USER:BROWSER_NAME}}'      => $user->browser_name,
                '{{USER:BROWSER_LANGUAGE}}'  => get_language_from_locale($user->browser_language),
            ];

            $email_template = get_email_template(
                $vars,
                htmlspecialchars_decode($broadcast->subject),
                $vars,
                convert_editorjs_json_to_html($broadcast->content)
            );

            /* Optional: tracking pixel & link rewriting */
            if(settings()->main->broadcasts_statistics_is_enabled) {
                $tracking_id = base64_encode('broadcast_id=' . $broadcast->broadcast_id . '&user_id=' . $user->user_id);
                $email_template->body .= '<img src="' . SITE_URL . 'broadcast?id=' . $tracking_id . '" style="display: none;" />';
                $email_template->body = preg_replace(
                    '/<a href=\"(.+)\"/',
                    '<a href="' . SITE_URL . 'broadcast?id=' . $tracking_id . '&url=$1"',
                    $email_template->body
                );
            }

            /* Clear addresses from previous iteration */
            $mail->clearAddresses();

            /* Add new email address */
            $mail->addAddress($user->email);

            /* Process the email title, template and body */
            extract(process_send_mail_template($email_template->subject, $email_template->body, ['is_broadcast' => true, 'is_system_email' => $broadcast->settings->is_system_email, 'anti_phishing_code' => $user->anti_phishing_code, 'language' => $user->language]));

            /* Set subject/body, then send */
            $mail->Subject = $title;
            $mail->Body = $email_template;
            $mail->AltBody = strip_tags($mail->Body);

            /* SEND */
            $mail->send();

            /* Track who we just emailed */
            $broadcast->sent_users_ids[] = $user->user_id;
            $newly_sent_user_ids[] = $user->user_id;

            Logger::users($user->user_id, 'broadcast.' . $broadcast->broadcast_id . '.sent');
        }

        /* Close this SMTP connection for the batch */
        $mail->smtpClose();

        /* Update broadcast once for the entire batch */
        db()->where('broadcast_id', $broadcast->broadcast_id)->update('broadcasts', [
            'sent_emails'             => db()->inc(count($newly_sent_user_ids)),
            'sent_users_ids'          => json_encode($broadcast->sent_users_ids),
            'status'                  => count($broadcast->users_ids) == count($broadcast->sent_users_ids) ? 'sent' : 'processing',
            'last_sent_email_datetime'=> get_date(),
        ]);

        /* Debugging */
        if(DEBUG) {
            echo '<br />' . "broadcast_id - {$broadcast->broadcast_id} | sent emails to users ids (total - " . count($newly_sent_user_ids) . "): " . implode(',', $newly_sent_user_ids) . '<br />';
        }

        $this->close();
    }

    public function push_notifications() {
        if(\Altum\Plugin::is_active('push-notifications')) {

            $this->initiate();

            /* Update cron job last run date */
            $this->update_cron_execution_datetimes('push_notifications_datetime');

            require_once \Altum\Plugin::get('push-notifications')->path . 'controllers/Cron.php';

            $this->close();
        }
    }

}
