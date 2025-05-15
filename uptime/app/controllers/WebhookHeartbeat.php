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

defined('ALTUMCODE') || die();

class WebhookHeartbeat extends Controller {

    public function index() {

        if(!settings()->monitors_heartbeats->heartbeats_is_enabled) {
            http_response_code(404);
            die();
        }

        /* Clean the heartbeat code */
        $code = isset($this->params[0]) ? query_clean($this->params[0]) : false;

        /* Get the details of the campaign from the database */
        $heartbeat = (new \Altum\Models\Heartbeats())->get_heartbeat_by_code($code);

        /* Make sure the campaign has access */
        if(!$heartbeat) {
            http_response_code(401);
            die();
        }

        $heartbeat->notifications = json_decode($heartbeat->notifications ?? '');
        $heartbeat->settings = json_decode($heartbeat->settings ?? '');
        $heartbeat->last_logs = json_decode($heartbeat->last_logs ?? '');

        if(!$heartbeat->is_enabled) {
            http_response_code(403);
            die();
        }

        /* Make sure we don't get spammed */
        /* 57 instead of 60 - to allow for small time differences */
        if($heartbeat->last_run_datetime && (new \DateTime($heartbeat->last_run_datetime))->modify('+57 seconds') > (new \DateTime())) {
            http_response_code(403);
            die();
        }

        /* Make sure to get the user data and confirm the user is ok */
        $user = (new \Altum\Models\User())->get_user_by_user_id($heartbeat->user_id);

        if(!$user) {
            http_response_code(403);
            die();
        }

        if(!$user->status) {
            http_response_code(403);
            die();
        }

        /* Make sure the user's plan is not already expired */
        if((new \DateTime()) > (new \DateTime($user->plan_expiration_date)) && $user->plan_id != 'free') {
            http_response_code(403);
            die();
        }

        /* Get the language for the user and set the timezone */
        \Altum\Date::$timezone = $user->timezone;

        /* Get available notification handlers */
        $notification_handlers = (new \Altum\Models\NotificationHandlers())->get_notification_handlers_by_user_id($user->user_id);

        $is_ok = 1;

        /* Insert the history log */
        $heartbeat_log_id = db()->insert('heartbeats_logs', [
            'heartbeat_id' => $heartbeat->heartbeat_id,
            'user_id' => $user->user_id,
            'is_ok' => $is_ok,
            'datetime' => get_date(),
        ]);

        /* Assuming, based on the run interval */
        $uptime_seconds_to_add = 0;
        switch($heartbeat->settings->run_interval_type) {
            case 'minutes':
                $uptime_seconds_to_add = $heartbeat->settings->run_interval * 60;
                break;

            case 'hours':
                $uptime_seconds_to_add = $heartbeat->settings->run_interval * 60 * 60;
                break;

            case 'days':
                $uptime_seconds_to_add = $heartbeat->settings->run_interval * 60 * 60 * 24;
                break;
        }
        $uptime_seconds = $heartbeat->uptime_seconds + $uptime_seconds_to_add;
        $downtime_seconds = $heartbeat->downtime_seconds;

        /* ^_^ */
        $uptime = $uptime_seconds > 0 ? $uptime_seconds / ($uptime_seconds + $downtime_seconds) * 100 : 0;
        $downtime = 100 - $uptime;
        $main_run_datetime = !$heartbeat->main_run_datetime || (!$heartbeat->is_ok && $is_ok) ? get_date() : $heartbeat->main_run_datetime;
        $last_run_datetime = get_date();

        /* Calculate expected next run */
        $next_run_datetime = (new \DateTime())
            ->modify('+' . $heartbeat->settings->run_interval . ' ' . $heartbeat->settings->run_interval_type)
            ->modify('+' . $heartbeat->settings->run_interval_grace . ' ' . $heartbeat->settings->run_interval_grace_type)
            ->format('Y-m-d H:i:s');

        /* Create / update an incident if needed */
        $incident_id = $heartbeat->incident_id;

        /* Close incident */
        if($is_ok && $heartbeat->incident_id) {

            /* Database query */
            db()->where('incident_id', $heartbeat->incident_id)->update('incidents', [
                'end_heartbeat_log_id' => $heartbeat_log_id,
                'end_datetime' => get_date(),
            ]);

            $incident_id = null;

            /* Get details about the incident */
            $heartbeat_incident = db()->where('incident_id', $heartbeat->incident_id)->getOne('incidents', ['start_datetime', 'end_datetime']);

            /* Get the language for the user */
            \Altum\Date::$timezone = $user->timezone;

            /* Processing the notification handlers */
            foreach($notification_handlers as $notification_handler) {
                if(!$notification_handler->is_enabled) continue;
                if(!in_array($notification_handler->notification_handler_id, $heartbeat->notifications->is_ok)) continue;

                switch($notification_handler->type) {
                    case 'email':

                        /* Prepare the email title */
                        $email_title = sprintf(l('cron.is_ok.title', $user->language), $heartbeat->name);

                        /* Prepare the View for the email content */
                        $data = [
                            'heartbeat_incident' => $heartbeat_incident,
                            'user' => $user,
                            'row' => $heartbeat
                        ];

                        $email_content = (new \Altum\View('partials/cron/heartbeat_is_ok', (array) $this))->run($data);

                        /* Send the email */
                        send_mail($notification_handler->settings->email, $email_title, $email_content, ['anti_phishing_code' => $user->anti_phishing_code, 'language' => $user->language]);

                        break;

                    case 'webhook':

                        fire_and_forget('post', $notification_handler->settings->webhook, [
                            'heartbeat_id' => $heartbeat->heartbeat_id,
                            'name' => $heartbeat->name,
                            'is_ok' => $is_ok,
                            'url' => url('heartbeat/' . $heartbeat->heartbeat_id)
                        ]);

                        break;

                    case 'slack':

                        try {
                            \Unirest\Request::post(
                                $notification_handler->settings->slack,
                                ['Accept' => 'application/json'],
                                \Unirest\Request\Body::json([
                                    'text' => sprintf(
                                        l('heartbeat.simple_notification.is_ok', $user->language),
                                        $heartbeat->name,
                                        "\r\n\r\n",
                                        url('heartbeat/' . $heartbeat->heartbeat_id)
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
                                                l('heartbeat.simple_notification.is_ok', $user->language),
                                                $heartbeat->name,
                                                "\r\n\r\n",
                                                url('heartbeat/' . $heartbeat->heartbeat_id)
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
                                        l('heartbeat.simple_notification.is_ok', $user->language),
                                        $heartbeat->name,
                                        urlencode("\r\n\r\n"),
                                        url('heartbeat/' . $heartbeat->heartbeat_id)
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
                                        l('heartbeat.simple_notification.is_ok', $user->language),
                                        $heartbeat->name,
                                        "\r\n\r\n",
                                        url('heartbeat/' . $heartbeat->heartbeat_id)
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
                                l('heartbeat.simple_notification.is_ok', $user->language),
                                $heartbeat->name,
                                "\r\n\r\n",
                                url('heartbeat/' . $heartbeat->heartbeat_id)
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
                                        l('heartbeat.simple_notification.is_ok', $user->language),
                                        $heartbeat->name,
                                        "\r\n\r\n",
                                        url('heartbeat/' . $heartbeat->heartbeat_id)
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
                                    'Url' => SITE_URL . 'twiml/heartbeat.simple_notification.is_ok?param1=' . $heartbeat->name . '&param2=&param3=' . url('heartbeat/' . $heartbeat->heartbeat_id),
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
                                        'name' => 'heartbeat_up',
                                        'language' => [
                                            'code' => \Altum\Language::$default_code
                                        ],
                                        'components' => [[
                                            'type' => 'body',
                                            'parameters' => [
                                                [
                                                    'type' => 'text',
                                                    'text' => $heartbeat->name
                                                ],
                                                [
                                                    'type' => 'text',
                                                    'text' => url('heartbeat/' . $heartbeat->heartbeat_id)
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
                            'title' => l('heartbeat.push_notification.is_not_ok.title', $heartbeat->language),
                            'description' => sprintf(l('heartbeat.push_notification.description', $heartbeat->language), $heartbeat->name, $heartbeat->target),
                            'url' => url('heartbeat/' . $heartbeat->heartbeat_id),
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
            $last_logs[] = isset($heartbeat->last_logs[$i]) ? $heartbeat->last_logs[$i] : [];
        }

        $last_logs[] = [
            'is_ok' => $is_ok,
            'datetime' => get_date(),
        ];

        /* Update the heartbeat */
        db()->where('heartbeat_id', $heartbeat->heartbeat_id)->update('heartbeats', [
            'incident_id' => $incident_id,
            'is_ok' => $is_ok,
            'uptime' => $uptime,
            'uptime_seconds' => $uptime_seconds,
            'downtime' => $downtime,
            'downtime_seconds' => $downtime_seconds,
            'total_runs' => db()->inc(),
            'main_run_datetime' => $main_run_datetime,
            'last_run_datetime' => $last_run_datetime,
            'next_run_datetime' => $next_run_datetime,
            'last_logs' => json_encode($last_logs),
        ]);

        /* Clear the cache */
        cache()->deleteItemsByTag('heartbeat_id=' . $heartbeat->heartbeat_id);

    }
}
