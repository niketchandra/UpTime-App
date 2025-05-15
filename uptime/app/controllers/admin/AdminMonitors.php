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

use Altum\Alerts;

defined('ALTUMCODE') || die();

class AdminMonitors extends Controller {

    public function index() {

        /* Prepare the filtering system */
        $filters = (new \Altum\Filters(['is_enabled', 'type', 'user_id', 'project_id', 'ping_servers_ids'], ['name', 'target'], ['monitor_id', 'last_datetime', 'datetime', 'name', 'uptime', 'total_checks', 'last_check_datetime', 'average_response_time'], [], ['ping_servers_ids' => 'json_contains']));
        $filters->set_default_order_by($this->user->preferences->monitors_default_order_by, $this->user->preferences->default_order_type ?? settings()->main->default_order_type);
        $filters->set_default_results_per_page($this->user->preferences->default_results_per_page ?? settings()->main->default_results_per_page);

        /* Prepare the paginator */
        $total_rows = database()->query("SELECT COUNT(*) AS `total` FROM `monitors` WHERE 1 = 1 {$filters->get_sql_where()}")->fetch_object()->total ?? 0;
        $paginator = (new \Altum\Paginator($total_rows, $filters->get_results_per_page(), $_GET['page'] ?? 1, url('admin/monitors?' . $filters->get_get() . '&page=%d')));

        /* Get the data */
        $monitors = [];
        $monitors_result = database()->query("
            SELECT
                `monitors`.*, `users`.`name` AS `user_name`, `users`.`email` AS `user_email`
            FROM
                `monitors`
            LEFT JOIN
                `users` ON `monitors`.`user_id` = `users`.`user_id`
            WHERE
                1 = 1
                {$filters->get_sql_where('monitors')}
                {$filters->get_sql_order_by('monitors')}

            {$paginator->get_sql_limit()}
        ");
        while($row = $monitors_result->fetch_object()) {
            $monitors[] = $row;
        }

        /* Export handler */
        process_export_csv($monitors, 'include', ['monitor_id', 'user_id', 'project_id', 'incident_id', 'name', 'type', 'target', 'port', 'ping_servers_ids', 'is_ok', 'uptime', 'uptime_seconds', 'downtime', 'downtime_seconds', 'average_response_time', 'total_checks', 'total_ok_checks', 'total_not_ok_checks', 'last_check_datetime', 'next_check_datetime', 'main_ok_datetime', 'last_ok_datetime', 'main_not_ok_datetime', 'last_not_ok_datetime', 'email_reports_is_enabled', 'email_reports_last_datetime', 'is_enabled', 'datetime', 'last_datetime'], sprintf(l('monitors.title')));
        process_export_json($monitors, 'include', ['monitor_id', 'user_id', 'project_id', 'incident_id', 'name', 'type', 'target', 'port', 'settings', 'details', 'ping_servers_ids', 'is_ok', 'uptime', 'uptime_seconds', 'downtime', 'downtime_seconds', 'average_response_time', 'total_checks', 'total_ok_checks', 'total_not_ok_checks', 'last_check_datetime', 'next_check_datetime', 'main_ok_datetime', 'last_ok_datetime', 'main_not_ok_datetime', 'last_not_ok_datetime', 'email_reports_is_enabled', 'email_reports_last_datetime', 'notifications', 'is_enabled', 'datetime', 'last_datetime'], sprintf(l('monitors.title')));

        /* Prepare the pagination view */
        $pagination = (new \Altum\View('partials/admin_pagination', (array) $this))->run(['paginator' => $paginator]);

        /* Main View */
        $data = [
            'monitors' => $monitors,
            'filters' => $filters,
            'pagination' => $pagination
        ];

        $view = new \Altum\View('admin/monitors/index', (array) $this);

        $this->add_view_content('content', $view->run($data));

    }

    public function bulk() {

        /* Check for any errors */
        if(empty($_POST)) {
            redirect('admin/monitors');
        }

        if(empty($_POST['selected'])) {
            redirect('admin/monitors');
        }

        if(!isset($_POST['type'])) {
            redirect('admin/monitors');
        }

        //ALTUMCODE:DEMO if(DEMO) Alerts::add_error('This command is blocked on the demo.');

        if(!\Altum\Csrf::check()) {
            Alerts::add_error(l('global.error_message.invalid_csrf_token'));
        }

        if(!Alerts::has_field_errors() && !Alerts::has_errors()) {

            set_time_limit(0);

            switch($_POST['type']) {
                case 'delete':

                    foreach($_POST['selected'] as $monitor_id) {

                        /* Delete the monitor */
                        db()->where('monitor_id', $monitor_id)->delete('monitors');

                        /* Clear the cache */
                        cache()->deleteItemsByTag('monitor_id=' . $monitor_id);

                    }

                    break;
            }

            /* Set a nice success message */
            Alerts::add_success(l('bulk_delete_modal.success_message'));

        }

        redirect('admin/monitors');
    }

    public function delete() {

        $monitor_id = isset($this->params[0]) ? (int) $this->params[0] : null;

        //ALTUMCODE:DEMO if(DEMO) Alerts::add_error('This command is blocked on the demo.');

        if(!\Altum\Csrf::check('global_token')) {
            Alerts::add_error(l('global.error_message.invalid_csrf_token'));
        }

        if(!$monitor = db()->where('monitor_id', $monitor_id)->getOne('monitors', ['monitor_id', 'name'])) {
            redirect('admin/monitors');
        }

        if(!Alerts::has_field_errors() && !Alerts::has_errors()) {

            /* Delete the monitor */
            db()->where('monitor_id', $monitor_id)->delete('monitors');

            /* Clear the cache */
            cache()->deleteItemsByTag('monitor_id=' . $monitor_id);

            /* Set a nice success message */
            Alerts::add_success(sprintf(l('global.success_message.delete1'), '<strong>' . $monitor->name . '</strong>'));

        }

        redirect('admin/monitors');
    }

}
