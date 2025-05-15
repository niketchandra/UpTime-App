<?php defined('ALTUMCODE') || die() ?>

<?php if(settings()->status_pages->additional_domains_is_enabled): ?>
    <?php $additional_domains = (new \Altum\Models\Domain())->get_available_additional_domains(); ?>
<?php endif ?>

<div>
    <?php if(settings()->monitors_heartbeats->monitors_is_enabled): ?>
        <?php $ping_servers = (new \Altum\Models\PingServers())->get_ping_servers(); ?>
        <?php $monitor_check_intervals = require APP_PATH . 'includes/monitor_check_intervals.php'; ?>

        <div class="d-flex justify-content-between align-items-center my-3">
            <div>
                <?= sprintf(l('global.plan_settings.monitors_limit'), '<strong>' . ($data->plan_settings->monitors_limit == -1 ? l('global.unlimited') : nr($data->plan_settings->monitors_limit)) . '</strong>') ?>
                <span class="mr-1" data-toggle="tooltip" title="<?= sprintf(l('global.plan_settings.monitors_check_intervals'), implode(', ', array_values(array_intersect_key($monitor_check_intervals, array_flip($data->plan_settings->monitors_check_intervals ?? []))))) ?>"><i class="fas fa-fw fa-xs fa-circle-question text-gray-500"></i></span>
            </div>

            <i class="fas fa-fw fa-sm <?= $data->plan_settings->monitors_limit ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' ?>"></i>
        </div>

        <div class="d-flex justify-content-between align-items-center my-3 <?= count($data->plan_settings->monitors_ping_servers ?? []) ? null : 'text-muted' ?>">
            <div>
                <?= sprintf(l('global.plan_settings.monitors_ping_servers'), '<strong>' . nr(count($data->plan_settings->monitors_ping_servers ?? [])) . '</strong>') ?>
                <span class="mr-1" data-toggle="tooltip" title="<?= sprintf(l('global.plan_settings.monitors_ping_servers_help'), implode(', ', array_map(function($ping_server_id) use($ping_servers) { return get_countries_array()[$ping_servers[$ping_server_id]->country_code] . ' (' . $ping_servers[$ping_server_id]->city_name . ')'; }, $data->plan_settings->monitors_ping_servers ?? []))) ?>"><i class="fas fa-fw fa-xs fa-circle-question text-gray-500"></i></span>
            </div>

            <i class="fas fa-fw fa-sm <?= count($data->plan_settings->monitors_ping_servers ?? []) ? 'fa-check-circle text-success' : 'fa-times-circle' ?>"></i>
        </div>
    <?php endif ?>

    <?php if(settings()->monitors_heartbeats->heartbeats_is_enabled): ?>
        <div class="d-flex justify-content-between align-items-center my-3">
            <div>
                <?= sprintf(l('global.plan_settings.heartbeats_limit'), '<strong>' . ($data->plan_settings->heartbeats_limit == -1 ? l('global.unlimited') : nr($data->plan_settings->heartbeats_limit)) . '</strong>') ?>
            </div>

            <i class="fas fa-fw fa-sm <?= $data->plan_settings->heartbeats_limit ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' ?>"></i>
        </div>
    <?php endif ?>

    <?php if(settings()->monitors_heartbeats->domain_names_is_enabled): ?>
        <div class="d-flex justify-content-between align-items-center my-3">
            <div>
                <?= sprintf(l('global.plan_settings.domain_names_limit'), '<strong>' . ($data->plan_settings->domain_names_limit == -1 ? l('global.unlimited') : nr($data->plan_settings->domain_names_limit)) . '</strong>') ?>
            </div>

            <i class="fas fa-fw fa-sm <?= $data->plan_settings->domain_names_limit ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' ?>"></i>
        </div>
    <?php endif ?>

    <?php if(settings()->monitors_heartbeats->dns_monitors_is_enabled): ?>
        <?php $dns_monitor_check_intervals = require APP_PATH . 'includes/dns_monitor_check_intervals.php'; ?>

        <div class="d-flex justify-content-between align-items-center my-3">
            <div>
                <?= sprintf(l('global.plan_settings.dns_monitors_limit'), '<strong>' . ($data->plan_settings->dns_monitors_limit == -1 ? l('global.unlimited') : nr($data->plan_settings->dns_monitors_limit)) . '</strong>') ?>
                <span class="mr-1" data-toggle="tooltip" title="<?= sprintf(l('global.plan_settings.dns_monitors_check_intervals'), implode(', ', array_values(array_intersect_key($dns_monitor_check_intervals, array_flip($data->plan_settings->dns_monitors_check_intervals ?? []))))) ?>"><i class="fas fa-fw fa-xs fa-circle-question text-gray-500"></i></span>
            </div>

            <i class="fas fa-fw fa-sm <?= $data->plan_settings->dns_monitors_limit ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' ?>"></i>
        </div>
    <?php endif ?>

    <?php if(settings()->monitors_heartbeats->server_monitors_is_enabled): ?>
        <?php $server_monitor_check_intervals = require APP_PATH . 'includes/server_monitor_check_intervals.php'; ?>

        <div class="d-flex justify-content-between align-items-center my-3">
            <div>
                <?= sprintf(l('global.plan_settings.server_monitors_limit'), '<strong>' . ($data->plan_settings->server_monitors_limit == -1 ? l('global.unlimited') : nr($data->plan_settings->server_monitors_limit)) . '</strong>') ?>
                <span class="mr-1" data-toggle="tooltip" title="<?= sprintf(l('global.plan_settings.server_monitors_check_intervals'), implode(', ', array_values(array_intersect_key($server_monitor_check_intervals, array_flip($data->plan_settings->server_monitors_check_intervals ?? []))))) ?>"><i class="fas fa-fw fa-xs fa-circle-question text-gray-500"></i></span>
            </div>

            <i class="fas fa-fw fa-sm <?= $data->plan_settings->server_monitors_limit ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' ?>"></i>
        </div>
    <?php endif ?>

    <?php if(settings()->status_pages->status_pages_is_enabled): ?>
        <div class="d-flex justify-content-between align-items-center my-3">
            <div>
                <?= sprintf(l('global.plan_settings.status_pages_limit'), '<strong>' . ($data->plan_settings->status_pages_limit == -1 ? l('global.unlimited') : nr($data->plan_settings->status_pages_limit)) . '</strong>') ?>
            </div>

            <i class="fas fa-fw fa-sm <?= $data->plan_settings->status_pages_limit ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' ?>"></i>
        </div>
    <?php endif ?>

    <?php if(settings()->links->projects_is_enabled): ?>
    <div class="d-flex justify-content-between align-items-center my-3">
        <div>
            <?= sprintf(l('global.plan_settings.projects_limit'), '<strong>' . ($data->plan_settings->projects_limit == -1 ? l('global.unlimited') : nr($data->plan_settings->projects_limit)) . '</strong>') ?>
        </div>

        <i class="fas fa-fw fa-sm <?= $data->plan_settings->projects_limit ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' ?>"></i>
    </div>
    <?php endif ?>

    <?php if(settings()->status_pages->domains_is_enabled): ?>
        <div class="d-flex justify-content-between align-items-center my-3">
            <div>
                <?= sprintf(l('global.plan_settings.domains_limit'), '<strong>' . ($data->plan_settings->domains_limit == -1 ? l('global.unlimited') : nr($data->plan_settings->domains_limit)) . '</strong>') ?>
            </div>

            <i class="fas fa-fw fa-sm <?= $data->plan_settings->domains_limit ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' ?>"></i>
        </div>
    <?php endif ?>

    <?php if(settings()->status_pages->additional_domains_is_enabled): ?>
        <div class="d-flex justify-content-between align-items-center my-3 <?= count($data->plan_settings->additional_domains ?? []) ? null : 'text-muted' ?>">
            <div>
                <?= sprintf(l('global.plan_settings.additional_domains'), '<strong>' . nr(count($data->plan_settings->additional_domains ?? [])) . '</strong>') ?>
                <span class="mr-1" data-toggle="tooltip" title="<?= sprintf(l('global.plan_settings.additional_domains_help'), implode(', ', array_map(function($domain_id) use($additional_domains) { return $additional_domains[$domain_id]->host ?? null; }, $data->plan_settings->additional_domains ?? []))) ?>"><i class="fas fa-fw fa-xs fa-circle-question text-gray-500"></i></span>
            </div>

            <i class="fas fa-fw fa-sm <?= count($data->plan_settings->additional_domains ?? []) ? 'fa-check-circle text-success' : 'fa-times-circle' ?>"></i>
        </div>
    <?php endif ?>

    <?php if(\Altum\Plugin::is_active('teams')): ?>
        <div class="d-flex justify-content-between align-items-center my-3">
            <div>
                <?= sprintf(l('global.plan_settings.teams_limit'), '<strong>' . ($data->plan_settings->teams_limit == -1 ? l('global.unlimited') : nr($data->plan_settings->teams_limit)) . '</strong>') ?>

                <span class="ml-1" data-toggle="tooltip" data-html="true" title="<?= sprintf(l('global.plan_settings.team_members_limit'), '<strong>' . ($data->plan_settings->team_members_limit == -1 ? l('global.unlimited') : nr($data->plan_settings->team_members_limit)) . '</strong>') ?>"><i class="fas fa-fw fa-xs fa-circle-question text-gray-500"></i></span>
            </div>

            <i class="fas fa-fw fa-sm <?= $data->plan_settings->teams_limit ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' ?>"></i>
        </div>
    <?php endif ?>

    <?php if(settings()->affiliate->is_enabled): ?>
        <div class="d-flex justify-content-between align-items-center my-3">
            <div>
                <?= sprintf(l('global.plan_settings.affiliate_commission_percentage'), '<strong>' . nr($data->plan_settings->affiliate_commission_percentage) . '%</strong>') ?>
            </div>

            <i class="fas fa-fw fa-sm <?= $data->plan_settings->affiliate_commission_percentage ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' ?>"></i>
        </div>
    <?php endif ?>

    <div class="d-flex justify-content-between align-items-center my-3">
        <div data-toggle="tooltip" title="<?= ($data->plan_settings->logs_retention == -1 ? '' : $data->plan_settings->logs_retention . ' ' . l('global.date.days')) ?>">
            <?= sprintf(l('global.plan_settings.logs_retention'), '<strong>' . ($data->plan_settings->logs_retention == -1 ? l('global.unlimited') : \Altum\Date::days_format($data->plan_settings->logs_retention)) . '</strong>') ?>
        </div>

        <i class="fas fa-fw fa-sm <?= $data->plan_settings->logs_retention ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' ?>"></i>
    </div>

    <div class="d-flex justify-content-between align-items-center my-3">
        <div data-toggle="tooltip" title="<?= ($data->plan_settings->statistics_retention == -1 ? '' : $data->plan_settings->statistics_retention . ' ' . l('global.date.days')) ?>">
            <?= sprintf(l('global.plan_settings.statistics_retention'), '<strong>' . ($data->plan_settings->statistics_retention == -1 ? l('global.unlimited') : \Altum\Date::days_format($data->plan_settings->statistics_retention)) . '</strong>') ?>
        </div>

        <i class="fas fa-fw fa-sm <?= $data->plan_settings->statistics_retention ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' ?>"></i>
    </div>

    <?php ob_start() ?>
    <?php $notification_handlers_icon = 'fa-times-circle text-muted'; ?>
    <div class='d-flex flex-column'>
        <?php foreach(array_keys(require APP_PATH . 'includes/notification_handlers.php') as $notification_handler): ?>
            <span class='my-1'><?= sprintf(l('global.plan_settings.notification_handlers_' . $notification_handler . '_limit'), '<strong>' . ($data->plan_settings->{'notification_handlers_' . $notification_handler . '_limit'} == -1 ? l('global.unlimited') : nr($data->plan_settings->{'notification_handlers_' . $notification_handler . '_limit'})) . '</strong>') ?></span>
            <?php if($data->plan_settings->{'notification_handlers_' . $notification_handler . '_limit'}) $notification_handlers_icon = 'fa-check-circle text-success'; ?>
        <?php endforeach ?>
    </div>
    <?php $html = ob_get_clean() ?>

    <div class="d-flex justify-content-between align-items-center my-3">
        <div>
            <?= l('global.plan_settings.notification_handlers_limit') ?>
            <span class="ml-1" data-toggle="tooltip" data-html="true" title="<?= $html ?>"><i class="fas fa-fw fa-xs fa-circle-question text-gray-500"></i></span>
        </div>

        <i class="fas fa-fw fa-sm <?= $notification_handlers_icon ?>"></i>
    </div>

    <?php if(settings()->status_pages->status_pages_is_enabled): ?>
        <div class="d-flex justify-content-between align-items-center my-3 <?= $data->plan_settings->analytics_is_enabled ? null : 'text-muted' ?>">
            <div>
                <?= l('global.plan_settings.analytics_is_enabled') ?>
                <span class="ml-1" data-toggle="tooltip" title="<?= l('global.plan_settings.analytics_is_enabled_help') ?>"><i class="fas fa-fw fa-xs fa-circle-question text-gray-500"></i></span>
            </div>

            <i class="fas fa-fw fa-sm <?= $data->plan_settings->analytics_is_enabled ? 'fa-check-circle text-success' : 'fa-times-circle' ?>"></i>
        </div>

        <?php if(\Altum\Plugin::is_active('pwa') && settings()->pwa->is_enabled): ?>
            <div class="d-flex justify-content-between align-items-center my-3 <?= $data->plan_settings->custom_pwa_is_enabled ? null : 'text-muted' ?>">
                <div>
                    <?= l('global.plan_settings.custom_pwa_is_enabled') ?>
                    <span class="ml-1" data-toggle="tooltip" title="<?= l('global.plan_settings.custom_pwa_is_enabled_help') ?>"><i class="fas fa-fw fa-xs fa-circle-question text-gray-500"></i></span>
                </div>

                <i class="fas fa-fw fa-sm <?= $data->plan_settings->custom_pwa_is_enabled ? 'fa-check text-success' : 'fa-times' ?>"></i>
            </div>
        <?php endif ?>

        <div class="d-flex justify-content-between align-items-center my-3 <?= $data->plan_settings->qr_is_enabled ? null : 'text-muted' ?>">
            <div>
                <?= l('global.plan_settings.qr_is_enabled') ?>
                <span class="ml-1" data-toggle="tooltip" title="<?= l('global.plan_settings.qr_is_enabled_help') ?>"><i class="fas fa-fw fa-xs fa-circle-question text-gray-500"></i></span>
            </div>

            <i class="fas fa-fw fa-sm <?= $data->plan_settings->qr_is_enabled ? 'fa-check-circle text-success' : 'fa-times-circle' ?>"></i>
        </div>

        <div class="d-flex justify-content-between align-items-center my-3 <?= $data->plan_settings->password_protection_is_enabled ? null : 'text-muted' ?>">
            <div>
                <?= l('global.plan_settings.password_protection_is_enabled') ?>
                <span class="ml-1" data-toggle="tooltip" title="<?= l('global.plan_settings.password_protection_is_enabled_help') ?>"><i class="fas fa-fw fa-xs fa-circle-question text-gray-500"></i></span>
            </div>

            <i class="fas fa-fw fa-sm <?= $data->plan_settings->password_protection_is_enabled ? 'fa-check-circle text-success' : 'fa-times-circle' ?>"></i>
        </div>

        <div class="d-flex justify-content-between align-items-center my-3 <?= $data->plan_settings->removable_branding_is_enabled ? null : 'text-muted' ?>">
            <div>
                <?= l('global.plan_settings.removable_branding_is_enabled') ?>
                <span class="ml-1" data-toggle="tooltip" title="<?= l('global.plan_settings.removable_branding_is_enabled_help') ?>"><i class="fas fa-fw fa-xs fa-circle-question text-gray-500"></i></span>
            </div>

            <i class="fas fa-fw fa-sm <?= $data->plan_settings->removable_branding_is_enabled ? 'fa-check-circle text-success' : 'fa-times-circle' ?>"></i>
        </div>

        <div class="d-flex justify-content-between align-items-center my-3 <?= $data->plan_settings->custom_url_is_enabled ? null : 'text-muted' ?>">
            <div>
                <?= l('global.plan_settings.custom_url_is_enabled') ?>
                <span class="ml-1" data-toggle="tooltip" title="<?= l('global.plan_settings.custom_url_is_enabled_help') ?>"><i class="fas fa-fw fa-xs fa-circle-question text-gray-500"></i></span>
            </div>

            <i class="fas fa-fw fa-sm <?= $data->plan_settings->custom_url_is_enabled ? 'fa-check-circle text-success' : 'fa-times-circle' ?>"></i>
        </div>

        <div class="d-flex justify-content-between align-items-center my-3 <?= $data->plan_settings->search_engine_block_is_enabled ? null : 'text-muted' ?>">
            <div>
                <?= l('global.plan_settings.search_engine_block_is_enabled') ?>
                <span class="ml-1" data-toggle="tooltip" title="<?= l('global.plan_settings.search_engine_block_is_enabled_help') ?>"><i class="fas fa-fw fa-xs fa-circle-question text-gray-500"></i></span>
            </div>

            <i class="fas fa-fw fa-sm <?= $data->plan_settings->search_engine_block_is_enabled ? 'fa-check-circle text-success' : 'fa-times-circle' ?>"></i>
        </div>

        <div class="d-flex justify-content-between align-items-center my-3 <?= $data->plan_settings->custom_css_is_enabled ? null : 'text-muted' ?>">
            <div>
                <?= l('global.plan_settings.custom_css_is_enabled') ?>
                <span class="ml-1" data-toggle="tooltip" title="<?= l('global.plan_settings.custom_css_is_enabled_help') ?>"><i class="fas fa-fw fa-xs fa-circle-question text-gray-500"></i></span>
            </div>

            <i class="fas fa-fw fa-sm <?= $data->plan_settings->custom_css_is_enabled ? 'fa-check-circle text-success' : 'fa-times-circle' ?>"></i>
        </div>

        <div class="d-flex justify-content-between align-items-center my-3 <?= $data->plan_settings->custom_js_is_enabled ? null : 'text-muted' ?>">
            <div>
                <?= l('global.plan_settings.custom_js_is_enabled') ?>
                <span class="ml-1" data-toggle="tooltip" title="<?= l('global.plan_settings.custom_js_is_enabled_help') ?>"><i class="fas fa-fw fa-xs fa-circle-question text-gray-500"></i></span>
            </div>

            <i class="fas fa-fw fa-sm <?= $data->plan_settings->custom_js_is_enabled ? 'fa-check-circle text-success' : 'fa-times-circle' ?>"></i>
        </div>
    <?php endif ?>

    <?php if(settings()->monitors_heartbeats->email_reports_is_enabled): ?>
        <div class="d-flex justify-content-between align-items-center my-3 <?= $data->plan_settings->email_reports_is_enabled ? null : 'text-muted' ?>">
            <div>
                <?= l('global.plan_settings.email_reports_is_enabled') ?>
                <span class="ml-1" data-toggle="tooltip" title="<?= l('global.plan_settings.email_reports_is_enabled_help') ?>"><i class="fas fa-fw fa-xs fa-circle-question text-gray-500"></i></span>
            </div>

            <i class="fas fa-fw fa-sm <?= $data->plan_settings->email_reports_is_enabled ? 'fa-check-circle text-success' : 'fa-times-circle' ?>"></i>
        </div>
    <?php endif ?>

    <?php if(settings()->main->api_is_enabled): ?>
        <div class="d-flex justify-content-between align-items-center my-3 <?= $data->plan_settings->api_is_enabled ? null : 'text-muted' ?>">
            <div>
                <?= l('global.plan_settings.api_is_enabled') ?>
                <span class="ml-1" data-toggle="tooltip" title="<?= l('global.plan_settings.api_is_enabled_help') ?>"><i class="fas fa-fw fa-xs fa-circle-question text-gray-500"></i></span>
            </div>

            <i class="fas fa-fw fa-sm <?= $data->plan_settings->api_is_enabled ? 'fa-check-circle text-success' : 'fa-times-circle' ?>"></i>
        </div>
    <?php endif ?>

    <?php if(settings()->main->white_labeling_is_enabled): ?>
        <div class="d-flex justify-content-between align-items-center my-3 <?= $data->plan_settings->white_labeling_is_enabled ? null : 'text-muted' ?>">
            <div>
                <?= l('global.plan_settings.white_labeling_is_enabled') ?>
                <span class="ml-1" data-toggle="tooltip" title="<?= l('global.plan_settings.white_labeling_is_enabled_help') ?>"><i class="fas fa-fw fa-xs fa-circle-question text-gray-500"></i></span>
            </div>

            <i class="fas fa-fw fa-sm <?= $data->plan_settings->white_labeling_is_enabled ? 'fa-check-circle text-success' : 'fa-times-circle' ?>"></i>
        </div>
    <?php endif ?>

    <?php $enabled_exports_count = count(array_filter((array) $data->plan_settings->export)); ?>

    <?php ob_start() ?>
    <div class='d-flex flex-column'>
        <?php foreach(['csv', 'json', 'pdf'] as $key): ?>
            <?php if($data->plan_settings->export->{$key}): ?>
                <span class='my-1'><?= sprintf(l('global.export_to'), mb_strtoupper($key)) ?></span>
            <?php else: ?>
                <s class='my-1'><?= sprintf(l('global.export_to'), mb_strtoupper($key)) ?></s>
            <?php endif ?>
        <?php endforeach ?>
    </div>
    <?php $html = ob_get_clean() ?>

    <div class="d-flex justify-content-between align-items-center my-3 <?= $enabled_exports_count ? null : 'text-muted' ?>">
        <div>
            <?= sprintf(l('global.plan_settings.export'), $enabled_exports_count) ?>
            <span class="mr-1" data-html="true" data-toggle="tooltip" title="<?= $html ?>"><i class="fas fa-fw fa-xs fa-circle-question text-gray-500"></i></span>
        </div>

        <i class="fas fa-fw fa-sm <?= $enabled_exports_count ? 'fa-check-circle text-success' : 'fa-times-circle' ?>"></i>
    </div>

    <div class="d-flex justify-content-between align-items-center my-3 <?= $data->plan_settings->no_ads ? null : 'text-muted' ?>">
        <div>
            <?= l('global.plan_settings.no_ads') ?>
            <span class="ml-1" data-toggle="tooltip" title="<?= l('global.plan_settings.no_ads_help') ?>"><i class="fas fa-fw fa-xs fa-circle-question text-gray-500"></i></span>
        </div>

        <i class="fas fa-fw fa-sm <?= $data->plan_settings->no_ads ? 'fa-check-circle text-success' : 'fa-times-circle' ?>"></i>
    </div>
</div>
