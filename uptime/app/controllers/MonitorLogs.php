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

use Altum\Date;
use Altum\Title;

defined('ALTUMCODE') || die();

class MonitorLogs extends Controller {

    public function index() {

        if(!settings()->monitors_heartbeats->monitors_is_enabled) {
            redirect('not-found');
        }

        \Altum\Authentication::guard();

        $monitor_id = isset($this->params[0]) ? (int) $this->params[0] : null;

        if(!$monitor = db()->where('monitor_id', $monitor_id)->where('user_id', $this->user->user_id)->getOne('monitors')) {
            redirect('monitors');
        }
        $monitor->details = json_decode($monitor->details);
        $monitor->settings = json_decode($monitor->settings ?? '');

        $start_date = isset($_GET['start_date']) ? query_clean($_GET['start_date']) : Date::get('', 4);
        $end_date = isset($_GET['end_date']) ? query_clean($_GET['end_date']) : Date::get('', 4);
        $date = \Altum\Date::get_start_end_dates($start_date, $end_date);

        /* Get available ping servers */
        $ping_servers = (new \Altum\Models\PingServers())->get_ping_servers();

        /* Prepare the filtering system */
        $filters = (new \Altum\Filters(['is_ok', 'ping_server_id'], ['response_status_code'], ['monitor_log_id', 'response_time', 'datetime']));
        $filters->set_default_order_by('monitor_log_id', $this->user->preferences->default_order_type ?? settings()->main->default_order_type);
        $filters->set_default_results_per_page($this->user->preferences->default_results_per_page ?? settings()->main->default_results_per_page);

        /* Prepare the paginator */
        $total_rows = database()->query("SELECT COUNT(*) AS `total` FROM `monitors_logs` WHERE `monitor_id` = {$monitor->monitor_id} AND (`datetime` BETWEEN '{$date->start_date_query}' AND '{$date->end_date_query}') {$filters->get_sql_where()}")->fetch_object()->total ?? 0;
        $paginator = (new \Altum\Paginator($total_rows, $filters->get_results_per_page(), $_GET['page'] ?? 1, url('monitor-logs/' . $monitor->monitor_id . '?' . $filters->get_get() . '&start_date=' . $start_date . '&end_date=' . $end_date . '&page=%d')));

        /* Get the required logs */
        $monitor_logs = [];
        $monitor_logs_result = database()->query("
            SELECT
                `monitor_log_id`,
                `ping_server_id`,
                `is_ok`,
                `response_time`,
                `response_status_code`,
                `error`,
                `datetime`
            FROM
                 `monitors_logs`
            WHERE
                `monitor_id` = {$monitor->monitor_id}
                AND (`datetime` BETWEEN '{$date->start_date_query}' AND '{$date->end_date_query}')
                {$filters->get_sql_where()}
                {$filters->get_sql_order_by()}
                  
            {$paginator->get_sql_limit()}
        ");

        /* Get monitor logs to calculate data and display charts */
        while($monitor_log = $monitor_logs_result->fetch_object()) {
            $monitor_logs[] = $monitor_log;
        }

        /* Set a custom title */
        Title::set(sprintf(l('monitor_logs.title'), $monitor->name));

        /* Export handler */
        process_export_csv($monitor_logs, 'include', ['is_ok', 'response_time', 'response_status_code', 'datetime'], sprintf(l('monitor_logs.title'), $monitor->name));
        process_export_json($monitor_logs, 'include', ['is_ok', 'response_time', 'response_status_code', 'datetime'], sprintf(l('monitor_logs.title'), $monitor->name));

        /* Prepare the pagination view */
        $pagination = (new \Altum\View('partials/pagination', (array) $this))->run(['paginator' => $paginator]);

        /* Prepare the view */
        $data = [
            'monitor' => $monitor,
            'monitor_logs' => $monitor_logs,
            'date' => $date,
            'ping_servers' => $ping_servers,
            'pagination' => $pagination,
            'filters' => $filters,
        ];

        $view = new \Altum\View('monitor-logs/index', (array) $this);

        $this->add_view_content('content', $view->run($data));

    }
}
