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

class ServerMonitor extends Controller {

    public function index() {

        if(!settings()->monitors_heartbeats->server_monitors_is_enabled) {
            redirect('not-found');
        }

        \Altum\Authentication::guard();

        $server_monitor_id = isset($this->params[0]) ? (int) $this->params[0] : null;

        if(!$server_monitor = db()->where('server_monitor_id', $server_monitor_id)->where('user_id', $this->user->user_id)->getOne('server_monitors')) {
            redirect('server-monitors');
        }
        $server_monitor->settings = json_decode($server_monitor->settings ?? '');

        $start_datetime = isset($_GET['start_date']) ? query_clean($_GET['start_date']) : Date::get('', 4);
        $end_datetime = isset($_GET['end_date']) ? query_clean($_GET['end_date']) : Date::get('', 4);
        $datetime = \Altum\Date::get_start_end_dates_new($start_datetime, $end_datetime);

        /* Get the required statistics */
        $server_monitor_logs = [];
        $server_monitor_logs_chart = [];

        $server_monitor_logs_result = database()->query("
            SELECT *
            FROM `server_monitors_logs`
            WHERE
                `server_monitor_id` = {$server_monitor->server_monitor_id}
                AND (`datetime` BETWEEN '{$datetime['query_start_date']}' AND '{$datetime['query_end_date']}')
        ");

        /* Get server_monitor logs to calculate data and display charts */
        while($server_monitor_log = $server_monitor_logs_result->fetch_object()) {
            $server_monitor_logs[] = $server_monitor_log;

            $label = $datetime['start_date'] == $datetime['end_date'] ? \Altum\Date::get($server_monitor_log->datetime, 3) : \Altum\Date::get($server_monitor_log->datetime, 1);

            $server_monitor_logs_chart[$label] = [
                'cpu_usage' => $server_monitor_log->cpu_usage,
                'ram_usage' => $server_monitor_log->ram_usage,
                'disk_usage' => $server_monitor_log->disk_usage,
                'cpu_load_1' => $server_monitor_log->cpu_load_1,
                'cpu_load_5' => $server_monitor_log->cpu_load_5,
                'cpu_load_15' => $server_monitor_log->cpu_load_15,
                'network_download' => $server_monitor_log->network_download,
                'network_upload' => $server_monitor_log->network_upload,
                'hour_minute_second_label' => \Altum\Date::get($server_monitor_log->datetime, 3)
            ];
        }

        /* Set a custom title */
        Title::set(sprintf(l('server_monitor.title'), $server_monitor->name));

        /* Export handler */
        process_export_csv($server_monitor_logs, 'include', ['server_monitor_id', 'cpu_usage', 'ram_usage', 'disk_usage', 'cpu_load_1', 'cpu_load_5', 'cpu_load_15', 'network_download', 'network_upload', 'datetime'], sprintf(l('server_monitor.title'), $server_monitor->name));
        process_export_json($server_monitor_logs, 'include', ['server_monitor_id', 'cpu_usage', 'ram_usage', 'disk_usage', 'cpu_load_1', 'cpu_load_5', 'cpu_load_15', 'network_download', 'network_upload', 'datetime'], sprintf(l('server_monitor.title'), $server_monitor->name));

        $server_monitor_logs_chart = get_chart_data($server_monitor_logs_chart);

        /* Prepare the view */
        $data = [
            'server_monitor' => $server_monitor,
            'server_monitor_logs_chart' => $server_monitor_logs_chart,
            'server_monitor_logs' => $server_monitor_logs,
            'total_server_monitor_logs' => count($server_monitor_logs),
            'datetime' => $datetime,
        ];

        $view = new \Altum\View('server-monitor/index', (array) $this);

        $this->add_view_content('content', $view->run($data));

    }

}
