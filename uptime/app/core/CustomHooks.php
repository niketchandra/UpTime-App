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

namespace Altum;

defined('ALTUMCODE') || die();

class CustomHooks {

    public static function user_initiate_registration($data = []) {

    }

    public static function user_finished_registration($data = []) {
        $plan_settings = json_decode($data['plan_settings']);

        if($plan_settings->notification_handlers_email_limit > 0) {
            db()->insert('notification_handlers', [
                'user_id' => $data['user_id'],
                'type' => 'email',
                'name' => $data['email'],
                'settings' => json_encode([
                    'email' => $data['email']
                ]),
                'datetime' => get_date(),
            ]);
        }
    }

    public static function user_delete($data = []) {

        /* Delete the potentially uploaded files on preference settings */
        if($data['user']->preferences->white_label_logo_light) {
            Uploads::delete_uploaded_file($data['user']->preferences->white_label_logo_light, 'users');
        }

        if($data['user']->preferences->white_label_logo_dark) {
            Uploads::delete_uploaded_file($data['user']->preferences->white_label_logo_dark, 'users');
        }

        if($data['user']->preferences->white_label_favicon) {
            Uploads::delete_uploaded_file($data['user']->preferences->white_label_favicon, 'users');
        }

        /* Delete everything related to the status pages that the user owns */
        $result = database()->query("SELECT `status_page_id` FROM `status_pages` WHERE `user_id` = {$data['user']->user_id}");

        while($status_page = $result->fetch_object()) {

            (new \Altum\Models\StatusPage())->delete($status_page->status_page_id);

        }

    }

    public static function user_payment_finished($data = []) {
        extract($data);

    }

    public static function generate_language_prefixes_to_skip($data = []) {

        $prefixes = [];

        /* Base features */
        if(!empty(settings()->main->index_url)) {
            $prefixes = array_merge($prefixes, ['index.']);
        }

        if(!settings()->email_notifications->contact) {
            $prefixes = array_merge($prefixes, ['contact.']);
        }

        if(!settings()->main->api_is_enabled) {
            $prefixes = array_merge($prefixes, ['api.', 'api_documentation.', 'account_api.']);
        }

        if(!settings()->internal_notifications->admins_is_enabled) {
            $prefixes = array_merge($prefixes, ['global.notifications.']);
        }

        if(!settings()->cookie_consent->is_enabled) {
            $prefixes = array_merge($prefixes, ['global.cookie_consent.']);
        }

        if(!settings()->ads->ad_blocker_detector_is_enabled){
            $prefixes = array_merge($prefixes, ['ad_blocker_detector_modal.']);
        }

        if(!settings()->content->blog_is_enabled) {
            $prefixes = array_merge($prefixes, ['blog.']);
        }

        if(!settings()->content->pages_is_enabled) {
            $prefixes = array_merge($prefixes, ['page.', 'pages.']);
        }

        if(!settings()->users->register_is_enabled) {
            $prefixes = array_merge($prefixes, ['register.']);
        }

        /* Extended license */
        if(!settings()->payment->is_enabled) {
            $prefixes = array_merge($prefixes, ['plan.', 'pay.', 'pay_thank_you.', 'account_payments.']);
        }

        if(!settings()->payment->is_enabled || !settings()->payment->taxes_and_billing_is_enabled) {
            $prefixes = array_merge($prefixes, ['pay_billing.']);
        }

        if(!settings()->payment->is_enabled || !settings()->payment->codes_is_enabled) {
            $prefixes = array_merge($prefixes, ['account_redeem_code.']);
        }

        if(!settings()->payment->is_enabled || !settings()->payment->invoice_is_enabled) {
            $prefixes = array_merge($prefixes, ['invoice.']);
        }


        /* Plugins */
        if(!\Altum\Plugin::is_active('pwa') || !settings()->pwa->is_enabled) {
            $prefixes = array_merge($prefixes, ['pwa_install.']);
        }

        if(!\Altum\Plugin::is_active('push-notifications') || !settings()->push_notifications->is_enabled) {
            $prefixes = array_merge($prefixes, ['push_notifications_modal.']);
        }

        if(!\Altum\Plugin::is_active('teams')) {
            $prefixes = array_merge($prefixes, ['teams.', 'team.', 'team_create.', 'team_update.', 'team_members.', 'team_member_create.', 'team_member_update.', 'teams_member.', 'teams_member_delete_modal.', 'teams_member_join_modal.', 'teams_member_login_modal.']);
        }

        if(!\Altum\Plugin::is_active('affiliate') || (\Altum\Plugin::is_active('affiliate') && !settings()->affiliate->is_enabled)) {
            $prefixes = array_merge($prefixes, ['referrals.', 'affiliate.']);
        }

        /* Per product features */
        if(!settings()->tools->is_enabled) {
            $prefixes = array_merge($prefixes, ['tools.']);
        }

        if(!settings()->status_pages->domains_is_enabled) {
            $prefixes = array_merge($prefixes, ['domains.', 'domain_create.', 'domain_update.', 'domain_delete_modal.']);
        }

        if(!settings()->monitors_heartbeats->monitors_is_enabled) {
            $prefixes = array_merge($prefixes, ['monitors.', 'monitor.', 'monitor_log.', 'monitor_logs.', 'monitor_create.', 'monitor_update.', 'cron.monitor_email_report.']);
        }

        if(!settings()->monitors_heartbeats->dns_monitors_is_enabled) {
            $prefixes = array_merge($prefixes, ['dns_monitors.', 'dns_monitor.', 'dns_monitor_logs.', 'dns_monitor_create.', 'dns_monitor_update.', 'cron.dns_monitor.']);
        }

        if(!settings()->monitors_heartbeats->server_monitors_is_enabled) {
            $prefixes = array_merge($prefixes, ['server_monitors.', 'server_monitor.', 'server_monitor_create.', 'server_monitor_update.', 'server_monitor_uninstall_modal.', 'server_monitor_install_modal.', 'cron.server_monitor.']);
        }

        if(!settings()->monitors_heartbeats->heartbeats_is_enabled) {
            $prefixes = array_merge($prefixes, ['heartbeats.', 'heartbeat.', 'heartbeat_create.', 'heartbeat_update.', 'cron.heartbeat_email_report.']);
        }

        if(!settings()->monitors_heartbeats->domain_names_is_enabled) {
            $prefixes = array_merge($prefixes, ['domain_names.', 'domain_name.', 'domain_name_create.', 'domain_name_update.']);
        }

        if(!settings()->status_pages->status_pages_is_enabled) {
            $prefixes = array_merge($prefixes, ['s_status_page.', 's_monitor.', 's_heartbeat.', 'status_pages.', 'status_page.', 'status_page_create.', 'status_page_update.', 'status_page_statistics.', 'status_page_qr.']);
        }

        if(!settings()->monitors_heartbeats->projects_is_enabled) {
            $prefixes = array_merge($prefixes, ['projects.', 'project_create.', 'project_update.']);
        }

        return $prefixes;

    }

}
