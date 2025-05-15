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

use Altum\Response;

defined('ALTUMCODE') || die();

class Spotlight extends Controller {

    public function index() {

        if(!settings()->main->admin_spotlight_is_enabled && !settings()->main->user_spotlight_is_enabled) {
            redirect('not-found');
        }

        if(!empty($_POST)) {
            redirect();
        }

        if(!\Altum\Csrf::check('global_token')) {
            Response::json(l('global.error_message.invalid_csrf_token'), 'error');
        }

        $available_pages = [];

        $available_pages[] = [
            'name' => l('index.title'),
            'url' => ''
        ];

        if(!is_logged_in()) {
            $available_pages[] = [
                'name' => l('login.title'),
                'url' => 'login'
            ];

            if(settings()->users->register_is_enabled) {
                $available_pages[] = [
                    'name' => l('register.title'),
                    'url' => 'register'
                ];
            }

            if(settings()->users->email_confirmation) {
                $available_pages[] = [
                    'name' => l('resend_activation.title'),
                    'url' => 'resend-activation'
                ];
            }

            $available_pages[] = [
                'name' => l('lost_password.title'),
                'url' => 'lost-password'
            ];

            if(settings()->payment->is_enabled) {
                $available_pages[] = [
                    'name' => l('plan.title'),
                    'url' => 'plan'
                ];
            }
        }

        if(is_logged_in()) {
            $available_pages[] = [
                'name' => l('dashboard.title'),
                'url' => 'dashboard'
            ];

            /* Per product */
            if(settings()->monitors_heartbeats->server_monitors_is_enabled) {
                $available_pages[] = [
                    'name' => l('server_monitors.title'),
                    'url' => 'server-monitors'
                ];

                $available_pages[] = [
                    'name' => l('server_monitor_create.title'),
                    'url' => 'server-monitor-create'
                ];
            }

            if(settings()->monitors_heartbeats->monitors_is_enabled) {
                $available_pages[] = [
                    'name' => l('monitors.title'),
                    'url' => 'monitors'
                ];

                $available_pages[] = [
                    'name' => l('monitor_create.title'),
                    'url' => 'monitor-create'
                ];
            }

            if(settings()->status_pages->status_pages_is_enabled) {
                $available_pages[] = [
                    'name' => l('status_pages.title'),
                    'url' => 'status-pages'
                ];

                $available_pages[] = [
                    'name' => l('status_page_create.title'),
                    'url' => 'status-page-create'
                ];
            }

            if(settings()->monitors_heartbeats->heartbeats_is_enabled) {
                $available_pages[] = [
                    'name' => l('heartbeats.title'),
                    'url' => 'heartbeats'
                ];

                $available_pages[] = [
                    'name' => l('heartbeat_create.title'),
                    'url' => 'heartbeat-create'
                ];
            }

            if(settings()->monitors_heartbeats->domain_names_is_enabled) {
                $available_pages[] = [
                    'name' => l('domain_names.title'),
                    'url' => 'domain-names'
                ];

                $available_pages[] = [
                    'name' => l('domain_name_create.title'),
                    'url' => 'domain-name-create'
                ];
            }

            if(settings()->monitors_heartbeats->dns_monitors_is_enabled) {
                $available_pages[] = [
                    'name' => l('dns_monitors.title'),
                    'url' => 'dns-monitors'
                ];

                $available_pages[] = [
                    'name' => l('dns_monitor_create.title'),
                    'url' => 'dns-monitor-create'
                ];
            }

            if(settings()->status_pages->domains_is_enabled) {
                $available_pages[] = [
                    'name' => l('domains.title'),
                    'url' => 'domains'
                ];
                $available_pages[] = [
                    'name' => l('domain_create.title'),
                    'url' => 'domain-create'
                ];
            }

            if(settings()->monitors_heartbeats->projects_is_enabled) {
                $available_pages[] = [
                    'name' => l('projects.title'),
                    'url' => 'projects'
                ];
                $available_pages[] = [
                    'name' => l('project_create.title'),
                    'url' => 'project-create'
                ];
            }

            if(settings()->tools->is_enabled && (settings()->tools->access == 'everyone' || (settings()->tools->access == 'users' && is_logged_in()))){
                $available_pages[] = [
                    'name' => l('tools.title'),
                    'url' => 'tools'
                ];
            }

            $available_pages[] = [
                'name' => l('notification_handlers.title'),
                'url'  => 'notification-handlers'
            ];
            $available_pages[] = [
                'name' => l('notification_handler_create.title'),
                'url'  => 'notification-handler-create'
            ];









            $available_pages[] = [
                'name' => l('account.title'),
                'url' => 'account'
            ];

            $available_pages[] = [
                'name' => l('account_preferences.title'),
                'url' => 'account-preferences'
            ];

            $available_pages[] = [
                'name' => l('account_plan.title'),
                'url' => 'account-plan'
            ];

            if(\Altum\Plugin::is_active('teams')) {
                $available_pages[] = [
                    'name' => l('teams_system.title'),
                    'url' => 'teams-system'
                ];

                $available_pages[] = [
                    'name' => l('teams.title'),
                    'url' => 'teams'
                ];

                $available_pages[] = [
                    'name' => l('teams_member.title'),
                    'url' => 'teams-member'
                ];
            }

            if(settings()->payment->is_enabled) {

                if(settings()->payment->codes_is_enabled) {
                    $available_pages[] = [
                        'name' => l('account_redeem_code.title'),
                        'url' => 'account-redeem-code'
                    ];
                }

                $available_pages[] = [
                    'name' => l('account_payments.title'),
                    'url' => 'account-payments'
                ];

                if(\Altum\Plugin::is_active('affiliate') && settings()->affiliate->is_enabled) {
                    $available_pages[] = [
                        'name' => l('referrals.title'),
                        'url' => 'referrals'
                    ];
                }
            }

            if(settings()->main->api_is_enabled) {
                $available_pages[] = [
                    'name' => l('account_api.title'),
                    'url' => 'account-api'
                ];
            }

            $available_pages[] = [
                'name' => l('account_logs.title'),
                'url' => 'account-logs'
            ];

            $available_pages[] = [
                'name' => l('account_delete.title'),
                'url' => 'account-delete'
            ];

            if(user()->type == 1) {
                if(file_exists(APP_PATH . 'languages/admin/' . \Altum\Language::$name . '#' . \Altum\Language::$code . '.php')) {
                    $admin_language = require APP_PATH . 'languages/admin/' . \Altum\Language::$name . '#' . \Altum\Language::$code . '.php';
                    \Altum\Language::$languages[\Altum\Language::$name]['content'] = \Altum\Language::$languages[\Altum\Language::$name]['content'] + $admin_language;
                }

                $available_pages[] = [
                    'name' => l('admin_index.title'),
                    'url' => 'admin'
                ];

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_users.title'),
                    'url' => 'admin/users'
                ];

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_user_create.title'),
                    'url' => 'admin/user-create'
                ];

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_users_logs.title'),
                    'url' => 'admin/users-logs'
                ];

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_redeemed_codes.title'),
                    'url' => 'admin/redeemed-codes'
                ];

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_blog_posts.title'),
                    'url' => 'admin/blog-posts'
                ];

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_blog_post_create.title'),
                    'url' => 'admin/blog-post-create'
                ];

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_blog_posts_categories.title'),
                    'url' => 'admin/blog-posts-categories'
                ];

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_blog_posts_category_create.title'),
                    'url' => 'admin/blog-posts-category-create'
                ];

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_pages.title'),
                    'url' => 'admin/pages'
                ];

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_page_create.title'),
                    'url' => 'admin/page-create'
                ];

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_pages_categories.title'),
                    'url' => 'admin/pages-categories'
                ];

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_pages_category_create.title'),
                    'url' => 'admin/pages-category-create'
                ];

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_plans.title'),
                    'url' => 'admin/plans'
                ];

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_plan_create.title'),
                    'url' => 'admin/plan-create'
                ];

                if(in_array(settings()->license->type, ['SPECIAL','Extended License', 'extended'])) {
                    $available_pages[] = [
                        'name' => l('global.menu.admin') . ' - ' . l('admin_codes.title'),
                        'url' => 'admin/codes'
                    ];

                    $available_pages[] = [
                        'name' => l('global.menu.admin') . ' - ' . l('admin_code_create.title'),
                        'url' => 'admin/code-create'
                    ];

                    $available_pages[] = [
                        'name' => l('global.menu.admin') . ' - ' . l('admin_taxes.title'),
                        'url' => 'admin/taxes'
                    ];

                    $available_pages[] = [
                        'name' => l('global.menu.admin') . ' - ' . l('admin_tax_create.title'),
                        'url' => 'admin/tax-create'
                    ];

                    if(\Altum\Plugin::is_active('affiliate')) {
                        $available_pages[] = [
                            'name' => l('global.menu.admin') . ' - ' . l('admin_affiliates_withdrawals.title'),
                            'url' => 'admin/affiliates-withdrawals'
                        ];
                    }

                    $available_pages[] = [
                        'name' => l('global.menu.admin') . ' - ' . l('admin_payments.title'),
                        'url' => 'admin/payments'
                    ];
                }

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_statistics.menu'),
                    'url' => 'admin/statistics'
                ];

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_plugins.title'),
                    'url' => 'admin/plugins'
                ];

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_languages.title'),
                    'url' => 'admin/languages'
                ];

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_language_create.title'),
                    'url' => 'admin/language-create'
                ];

                $pages = [
                    'main',
                    'users',
                    'content'
                ];

                foreach ($pages as $page) {
                    $available_pages[] = [
                        'name' => l('global.menu.admin') . ' - ' . sprintf(l('admin_settings.title'), l('admin_settings.' . $page . '.tab')),
                        'url'  => 'admin/settings/' . $page
                    ];
                }

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . sprintf(l('admin_settings.title'), l('admin_settings.status_pages.tab')),
                    'url'  => 'admin/settings/status_pages'
                ];

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . sprintf(l('admin_settings.title'), l('admin_settings.monitors_heartbeats.tab')),
                    'url'  => 'admin/settings/monitors_heartbeats'
                ];

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . sprintf(l('admin_settings.title'), l('admin_settings.notification_handlers.tab')),
                    'url'  => 'admin/settings/notification_handlers'
                ];

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . sprintf(l('admin_settings.title'), l('admin_settings.tools.tab')),
                    'url'  => 'admin/settings/tools'
                ];


                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . sprintf(l('admin_settings.title'), l('admin_settings.payment.tab')),
                    'url'  => 'admin/settings/payment'
                ];

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . sprintf(l('admin_settings.title'), l('admin_settings.business.tab')),
                    'url'  => 'admin/settings/business'
                ];

                foreach(require APP_PATH . 'includes/payment_processors.php' as $key => $value) {
                    $available_pages[] = [
                        'name' => l('global.menu.admin') . ' - ' . sprintf(l('admin_settings.title'), l('admin_settings.' . $key . '.tab')),
                        'url'  => 'admin/settings/' . $key
                    ];
                }

                $pages = [
                    'affiliate',
                    'captcha',
                    'facebook',
                    'google',
                    'twitter',
                    'discord',
                    'linkedin',
                    'microsoft',
                    'ads',
                    'cookie_consent',
                    'socials',
                    'smtp',
                    'theme',
                    'custom',
                    'announcements',
                    'internal_notifications',
                    'email_notifications',
                    'push_notifications',
                    'webhooks',
                    'offload',
                    'pwa',
                    'image_optimizer',
                    'sso',
                    'cron',
                    'health',
                    'cache',
                    'license',
                    'support'
                ];

                foreach ($pages as $page) {
                    $available_pages[] = [
                        'name' => l('global.menu.admin') . ' - ' . sprintf(l('admin_settings.title'), l('admin_settings.' . $page . '.tab')),
                        'url'  => 'admin/settings/' . $page
                    ];
                }


                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_api_documentation.title'),
                    'url' => 'admin/api-documentation'
                ];

                if(\Altum\Plugin::is_active('teams')) {
                    $available_pages[] = [
                        'name' => l('global.menu.admin') . ' - ' . l('admin_teams.title'),
                        'url' => 'admin/teams'
                    ];

                    $available_pages[] = [
                        'name' => l('global.menu.admin') . ' - ' . l('admin_team_members.title'),
                        'url' => 'admin/team-members'
                    ];
                }

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_broadcasts.title'),
                    'url' => 'admin/broadcasts'
                ];

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_broadcast_create.title'),
                    'url' => 'admin/broadcast-create'
                ];

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_internal_notifications.title'),
                    'url' => 'admin/internal-notifications'
                ];

                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_internal_notification_create.title'),
                    'url' => 'admin/internal-notification-create'
                ];

                if(\Altum\Plugin::is_active('push-notifications')) {
                    $available_pages[] = [
                        'name' => l('global.menu.admin') . ' - ' . l('admin_push_subscribers.title'),
                        'url' => 'admin/push-subscribers'
                    ];

                    $available_pages[] = [
                        'name' => l('global.menu.admin') . ' - ' . l('admin_push_notifications.title'),
                        'url' => 'admin/push-notifications'
                    ];

                    $available_pages[] = [
                        'name' => l('global.menu.admin') . ' - ' . l('admin_push_notification_create.title'),
                        'url' => 'admin/push-notification-create'
                    ];
                }

                /* Per product */
                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_status_pages.menu'),
                    'url'  => 'admin/status-pages'
                ];
                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_monitors.menu'),
                    'url'  => 'admin/monitors'
                ];
                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_heartbeats.menu'),
                    'url'  => 'admin/heartbeats'
                ];
                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_domain_names.menu'),
                    'url'  => 'admin/domain-names'
                ];
                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_dns_monitors.menu'),
                    'url'  => 'admin/dns-monitors'
                ];
                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_server_monitors.menu'),
                    'url'  => 'admin/server-monitors'
                ];
                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_projects.menu'),
                    'url'  => 'admin/projects'
                ];
                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_domains.menu'),
                    'url'  => 'admin/domains'
                ];
                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_domain_create.menu'),
                    'url'  => 'admin/domain-create'
                ];
                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_notification_handlers.menu'),
                    'url'  => 'admin/notification-handlers'
                ];
                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_ping_servers.menu'),
                    'url'  => 'admin/ping-servers'
                ];
                $available_pages[] = [
                    'name' => l('global.menu.admin') . ' - ' . l('admin_ping_server_create.menu'),
                    'url'  => 'admin/ping-server-create'
                ];





            }

            $available_pages[] = [
                'name' => l('global.menu.logout'),
                'url' => 'logout'
            ];
        }

        if(settings()->email_notifications->contact && !empty(settings()->email_notifications->emails)) {
            $available_pages[] = [
                'name' => l('contact.title'),
                'url' => 'contact'
            ];
        }

        if(settings()->main->api_is_enabled) {
            $available_pages[] = [
                'name' => l('api_documentation.title'),
                'url' => 'api-documentation'
            ];
        }

        if(settings()->payment->is_enabled) {
            if(\Altum\Plugin::is_active('affiliate') && settings()->affiliate->is_enabled) {
                $available_pages[] = [
                    'name' => l('affiliate.title'),
                    'url' => 'affiliate'
                ];
            }
        }

        if(settings()->content->blog_is_enabled) {
            $available_pages[] = [
                'name' => l('blog.title'),
                'url' => 'blog'
            ];
        }

        if(settings()->content->pages_is_enabled) {
            $available_pages[] = [
                'name' => l('pages.title'),
                'url' => 'pages'
            ];
        }

        Response::json('', 'success', $available_pages);

    }

}
