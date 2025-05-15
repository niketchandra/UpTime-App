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

namespace Altum\Models;

defined('ALTUMCODE') || die();

class MonitorsLogs extends Model {

    public function get_monitor_logs_by_monitor_id_and_start_datetime_and_end_datetime($monitor_id, $start_datetime, $end_datetime) {

        /* Get all available monitor logs */
        $monitor_logs = [];

        /* Try to check if the status_page posts exists via the cache */
        $cache_instance = cache()->getItem('s_monitor_logs?monitor_id=' . $monitor_id . '&start_datetime=' . md5($start_datetime) . '&end_datetime=' . md5($end_datetime));

        /* Set cache if not existing */
        if(is_null($cache_instance->get())) {

            /* Get data from the database */
            $monitor_logs_result = database()->query("SELECT * FROM `monitors_logs` WHERE `monitor_id` = {$monitor_id} AND (`datetime` BETWEEN '{$start_datetime}' AND '{$end_datetime}')");

            while($row = $monitor_logs_result->fetch_object()) $monitor_logs[] = $row;

            cache()->save(
                $cache_instance->set($monitor_logs)->expiresAfter(CACHE_DEFAULT_SECONDS)->addTag('monitor_id=' . $monitor_id)
            );

        } else {

            /* Get cache */
            $monitor_logs = $cache_instance->get();

        }

        return $monitor_logs;

    }

}
