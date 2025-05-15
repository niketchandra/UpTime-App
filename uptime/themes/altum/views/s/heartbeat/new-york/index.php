<?php defined('ALTUMCODE') || die() ?>

<?= $this->views['header'] ?>

<div class="container mt-4">

    <?php if(settings()->main->breadcrumbs_is_enabled): ?>
        <nav aria-label="breadcrumb">
            <ol class="custom-breadcrumbs small">
                <li>
                    <a href="<?= $data->status_page->full_url ?>"><?= l('s_status_page.breadcrumb') ?></a> <div class="svg-sm text-muted d-inline-block"><?= include_view(ASSETS_PATH . '/images/icons/chevron-right.svg') ?></div>
                </li>
                <li class="active" aria-current="page"><?= sprintf(l('s_heartbeat.breadcrumb'), $data->heartbeat->name) ?></li>
            </ol>
        </nav>
    <?php endif ?>

    <div class="card bg-blue-900 border-0">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <?php if($data->heartbeat->is_ok): ?>
                    <div class="svg-head-status text-primary-400 d-inline-block"><?= include_view(ASSETS_PATH . '/images/icons/check-circle.svg') ?></div>
                    <div class="ml-3">
                        <span class="text-white h3"><?= sprintf(l('s_heartbeat.heartbeat_status_ok'), $data->heartbeat->name) ?></span>
                        <div>
                            <span class="text-gray-400"><?= sprintf(l('s_heartbeat.last_run_datetime'), \Altum\Date::get_timeago($data->heartbeat->last_run_datetime)) ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="svg-head-status text-danger d-inline-block"><?= include_view(ASSETS_PATH . '/images/icons/x-circle.svg') ?></div>
                    <div class="ml-3">
                        <span class="text-white h3"><?= sprintf(l('s_heartbeat.heartbeat_status_not_ok'), $data->heartbeat->name) ?></span>
                        <div>
                            <span class="text-gray-400"><?= sprintf(l('s_heartbeat.last_run_datetime'), \Altum\Date::get_timeago($data->heartbeat->last_run_datetime)) ?></span>
                        </div>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>

    <div class="row justify-content-between my-4">
        <div class="col-12 col-xl mb-3 mb-xl-0">
            <div class="card h-100">
                <div class="card-body d-flex">

                    <div>
                        <div class="card border-0 bg-blue-50 text-blue-800 mr-3">
                            <div class="p-3 d-flex align-items-center justify-content-between">
                                <div class="svg-card-icon d-inline-block"><?= include_view(ASSETS_PATH . '/images/icons/globe-alt.svg') ?></div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <span class="text-muted"><?= l('heartbeat.uptime') ?></span>
                        <div class="d-flex align-items-center">
                            <div class="card-title h5 m-0"><?= $data->total_heartbeat_logs ? nr($data->heartbeat_logs_data['uptime'], settings()->monitors_heartbeats->decimals) . '%' : '?' ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl mb-3 mb-xl-0">
            <div class="card h-100">
                <div class="card-body d-flex">

                    <div>
                        <div class="card border-0 bg-blue-50 text-blue-800 mr-3">
                            <div class="p-3 d-flex align-items-center justify-content-between">
                                <div class="svg-card-icon d-inline-block"><?= include_view(ASSETS_PATH . '/images/icons/x-circle.svg') ?></div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <span class="text-muted"><?= l('heartbeat.total_incidents') ?></span>
                        <div class="d-flex align-items-center">
                            <div class="card-title h5 m-0"><?= $data->total_heartbeat_logs ? nr(count($data->heartbeat_incidents)) : '?' ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="my-4 d-flex justify-content-between align-items-center">
        <div></div>
        <button
                id="daterangepicker"
                type="button"
                class="btn btn-sm btn-light"
                data-min-date="<?= \Altum\Date::get($data->heartbeat->datetime, 4) ?>"
                data-max-date="<?= \Altum\Date::get('', 4) ?>"
        >
            <div class="svg-sm text-muted d-inline-block mr-1">
                <?= include_view(ASSETS_PATH . '/images/icons/calendar.svg') ?>
            </div>
            <span class="">
                <?php if($data->date->start_date == $data->date->end_date): ?>
                    <?= \Altum\Date::get($data->date->start_date, 2, \Altum\Date::$default_timezone) ?>
                <?php else: ?>
                    <?= \Altum\Date::get($data->date->start_date, 2, \Altum\Date::$default_timezone) . ' - ' . \Altum\Date::get($data->date->end_date, 2, \Altum\Date::$default_timezone) ?>
                <?php endif ?>
            </span>
            <i class="fas fa-fw fa-caret-down d-none d-lg-inline-block ml-lg-1"></i>
        </button>
    </div>

    <?php if($data->total_heartbeat_logs): ?>
        <div class="card my-4">
            <div class="card-body">
                <div class="chart-container" style="height: 300px;">
                    <canvas id="heartbeat_logs_chart"></canvas>
                </div>
            </div>
        </div>

        <?php if(count($data->heartbeat_incidents)): ?>
            <div class="my-4">
                <div class="table-responsive table-custom-container">
                    <table class="table table-custom">
                        <thead>
                        <tr>
                            <th colspan="3"><?= l('incidents.header') ?></th>
                        </tr>
                        <tr>
                            <th><?= l('incidents.start_datetime') ?></th>
                            <th><?= l('incidents.end_datetime') ?></th>
                            <th><?= l('incidents.length') ?></th>
                            <th><?= l('incidents.comment') ?></th>
                        </tr>
                        <tbody>
                        <?php foreach($data->heartbeat_incidents as $heartbeat_incident): ?>
                            <tr>
                                <td class="text-truncate text-muted">
                                <span data-toggle="tooltip" title="<?= \Altum\Date::get($heartbeat_incident->start_datetime, 1) ?>">
                                    <?= \Altum\Date::get_timeago($heartbeat_incident->start_datetime) ?>
                                </span>
                                </td>

                                <td class="text-truncate">
                                    <?php if($heartbeat_incident->end_datetime): ?>
                                        <span class="text-success" data-toggle="tooltip" title="<?= \Altum\Date::get($heartbeat_incident->end_datetime, 1) ?>">
                                        <?= \Altum\Date::get_timeago($heartbeat_incident->end_datetime) ?>
                                    </span>
                                    <?php else: ?>
                                        <span class="text-danger">
                                        <?= l('incidents.end_datetime_null') ?>
                                    </span>
                                    <?php endif ?>
                                </td>

                                <td class="text-truncate">
                                    <?= \Altum\Date::get_elapsed_time($heartbeat_incident->start_datetime, $heartbeat_incident->end_datetime) ?>
                                </td>

                                <td class="text-truncate">
                                    <?= $heartbeat_incident->comment ?>
                                </td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif ?>

    <?php else: ?>

        <div class="card my-4">
            <div class="card-body">
                <span class="text-muted"><?= l('s_heartbeat.heartbeat_logs_no_data') ?></span>
            </div>
        </div>

    <?php endif ?>

    <div class="mt-5">
    <div><small class="text-muted"><?= sprintf(l('s_status_page.timezone'), $data->status_page->timezone) ?></small></div>
    <?php if($data->total_heartbeat_logs): ?>
        <div><small class="text-muted"><?= sprintf(l('s_heartbeat.total_heartbeat_logs'), nr($data->total_heartbeat_logs)) ?></small></div>
    <?php endif ?>
    <?php if($data->status_page->settings->auto_refresh): ?>
        <div><small class="text-muted"><?= sprintf(l('s_status_page.auto_refresh'), $data->status_page->settings->auto_refresh) ?></small></div>

    <?php ob_start() ?>
        <script>
            'use strict';

            setInterval(() => {
                location.reload();
            }, <?= (int) $data->status_page->settings->auto_refresh * 60000 ?>);
        </script>
        <?php \Altum\Event::add_content(ob_get_clean(), 'javascript') ?>
    <?php endif ?>
    </div>
</div>

<?php if($data->status_page->settings->display_share_buttons): ?>
    <?= include_view(THEME_PATH . 'views/s/partials/share.php', ['external_url' => $data->status_page->full_url . 'heartbeat/' . $data->heartbeat->heartbeat_id]) ?>
<?php endif ?>

<?php ob_start() ?>
<link href="<?= ASSETS_FULL_URL . 'css/libraries/daterangepicker.min.css?v=' . PRODUCT_CODE ?>" rel="stylesheet" media="screen,print">
<?php \Altum\Event::add_content(ob_get_clean(), 'head') ?>

<?php require THEME_PATH . 'views/partials/js_chart_defaults.php' ?>

<?php ob_start() ?>
<script src="<?= ASSETS_FULL_URL . 'js/libraries/moment.min.js?v=' . PRODUCT_CODE ?>"></script>
<script src="<?= ASSETS_FULL_URL . 'js/libraries/daterangepicker.min.js?v=' . PRODUCT_CODE ?>"></script>
<script src="<?= ASSETS_FULL_URL . 'js/libraries/moment-timezone-with-data-10-year-range.min.js?v=' . PRODUCT_CODE ?>"></script>

<script>
    moment.tz.setDefault(<?= json_encode($data->status_page->timezone) ?>);

    /* Daterangepicker */
    $('#daterangepicker').daterangepicker({
        maxSpan: {
            days: 30
        },
        startDate: <?= json_encode($data->date->start_date) ?>,
        endDate: <?= json_encode($data->date->end_date) ?>,
        minDate: $('#daterangepicker').data('min-date'),
        maxDate: $('#daterangepicker').data('max-date'),
        ranges: {
            <?= json_encode(l('global.date.today')) ?>: [moment(), moment()],
            <?= json_encode(l('global.date.yesterday')) ?>: [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            <?= json_encode(l('global.date.last_7_days')) ?>: [moment().subtract(6, 'days'), moment()],
            <?= json_encode(l('global.date.last_30_days')) ?>: [moment().subtract(29, 'days'), moment()],
            <?= json_encode(l('global.date.this_month')) ?>: [moment().startOf('month'), moment().endOf('month')],
            <?= json_encode(l('global.date.last_month')) ?>: [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
        },
        alwaysShowCalendars: true,
        linkedCalendars: false,
        singleCalendar: true,
        locale: <?= json_encode(require APP_PATH . 'includes/daterangepicker_translations.php') ?>,
    }, (start, end, label) => {

        /* Redirect */
        redirect(`<?= $data->status_page->full_url . 'heartbeat/' . $data->heartbeat->heartbeat_id ?>?start_date=${start.format('YYYY-MM-DD')}&end_date=${end.format('YYYY-MM-DD')}`, true);

    });

    <?php if($data->total_heartbeat_logs): ?>
    let css = window.getComputedStyle(document.body)

    /* Chart */
    let heartbeat_logs_chart = document.getElementById('heartbeat_logs_chart').getContext('2d');

    let is_ok_color = css.getPropertyValue('--primary');
    let is_not_ok_color = css.getPropertyValue('--danger');

    /* Generate colors based on if heartbeat is ok */
    let is_ok_colors = [];
    <?= $data->heartbeat_logs_chart['is_ok'] ?? '[]' ?>.forEach(is_ok => {
        is_ok_colors.push(parseInt(is_ok) ? is_ok_color : is_not_ok_color);
    })

    /* Tooltip titles */
    let tooltip_titles = <?= $data->heartbeat_logs_chart['labels'] ?>;
    chart_options.plugins.tooltip.callbacks.title = (context) => {
        return tooltip_titles[context[0].dataIndex];
    }

    chart_options.plugins.tooltip.callbacks.label = (context) => {
        return `${context.dataset.label}: ${context.raw == 0.25 ? <?= json_encode(l('global.no')) ?> : <?= json_encode(l('global.yes')) ?>}`;
    }

    /* Display chart */
    new Chart(heartbeat_logs_chart, {
        type: 'bar',
        data: {
            labels: <?= $data->heartbeat_logs_chart['hour_minute_second_label'] ?>,
            datasets: [
                {
                    label: <?= json_encode(l('heartbeat.is_ok')) ?>,
                    data: <?= $data->heartbeat_logs_chart['is_ok_chart'] ?? '[]' ?>,
                    backgroundColor: is_ok_colors,
                    borderColor: is_ok_colors,
                    fill: true
                }
            ]
        },
        options: chart_options
    });
    <?php endif ?>

</script>
<?php \Altum\Event::add_content(ob_get_clean(), 'javascript') ?>
