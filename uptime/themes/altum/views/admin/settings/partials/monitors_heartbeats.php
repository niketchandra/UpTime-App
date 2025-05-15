<?php defined('ALTUMCODE') || die() ?>

<div>
    <div class="form-group custom-control custom-switch">
        <input id="monitors_is_enabled" name="monitors_is_enabled" type="checkbox" class="custom-control-input" <?= settings()->monitors_heartbeats->monitors_is_enabled ? 'checked="checked"' : null?>>
        <label class="custom-control-label" for="monitors_is_enabled"><?= l('admin_settings.monitors_heartbeats.monitors_is_enabled') ?></label>
    </div>

    <div class="form-group custom-control custom-switch">
        <input id="dns_monitors_is_enabled" name="dns_monitors_is_enabled" type="checkbox" class="custom-control-input" <?= settings()->monitors_heartbeats->dns_monitors_is_enabled ? 'checked="checked"' : null?>>
        <label class="custom-control-label" for="dns_monitors_is_enabled"><?= l('admin_settings.monitors_heartbeats.dns_monitors_is_enabled') ?></label>
    </div>

    <div class="form-group custom-control custom-switch">
        <input id="server_monitors_is_enabled" name="server_monitors_is_enabled" type="checkbox" class="custom-control-input" <?= settings()->monitors_heartbeats->server_monitors_is_enabled ? 'checked="checked"' : null?>>
        <label class="custom-control-label" for="server_monitors_is_enabled"><?= l('admin_settings.monitors_heartbeats.server_monitors_is_enabled') ?></label>
    </div>

    <div class="form-group custom-control custom-switch">
        <input id="heartbeats_is_enabled" name="heartbeats_is_enabled" type="checkbox" class="custom-control-input" <?= settings()->monitors_heartbeats->heartbeats_is_enabled ? 'checked="checked"' : null?>>
        <label class="custom-control-label" for="heartbeats_is_enabled"><?= l('admin_settings.monitors_heartbeats.heartbeats_is_enabled') ?></label>
    </div>

    <div class="form-group custom-control custom-switch">
        <input id="domain_names_is_enabled" name="domain_names_is_enabled" type="checkbox" class="custom-control-input" <?= settings()->monitors_heartbeats->domain_names_is_enabled ? 'checked="checked"' : null?>>
        <label class="custom-control-label" for="domain_names_is_enabled"><?= l('admin_settings.monitors_heartbeats.domain_names_is_enabled') ?></label>
    </div>

    <div class="form-group">
        <label for="email_reports_is_enabled"><i class="fas fa-fw fa-sm fa-envelope text-muted mr-1"></i> <?= l('admin_settings.monitors_heartbeats.email_reports_is_enabled') ?></label>
        <select id="email_reports_is_enabled" name="email_reports_is_enabled" class="custom-select">
            <option value="0" <?= !settings()->monitors_heartbeats->email_reports_is_enabled ? 'selected="selected"' : null ?>><?= l('global.disabled') ?></option>
            <option value="weekly" <?= settings()->monitors_heartbeats->email_reports_is_enabled == 'weekly' ? 'selected="selected"' : null ?>><?= l('admin_settings.monitors_heartbeats.email_reports_is_enabled_weekly') ?></option>
            <option value="monthly" <?= settings()->monitors_heartbeats->email_reports_is_enabled == 'monthly' ? 'selected="selected"' : null ?>><?= l('admin_settings.monitors_heartbeats.email_reports_is_enabled_monthly') ?></option>
        </select>
        <small class="form-text text-muted"><?= l('admin_settings.monitors_heartbeats.email_reports_is_enabled_help') ?></small>
    </div>

    <div class="form-group custom-control custom-switch">
        <input id="monitors_ipv6_ping_is_enabled" name="monitors_ipv6_ping_is_enabled" type="checkbox" class="custom-control-input" <?= settings()->monitors_heartbeats->monitors_ipv6_ping_is_enabled ? 'checked="checked"' : null?>>
        <label class="custom-control-label" for="monitors_ipv6_ping_is_enabled"><?= l('admin_settings.monitors_heartbeats.monitors_ipv6_ping_is_enabled') ?></label>
        <small class="form-text text-muted"><?= l('admin_settings.monitors_heartbeats.monitors_ipv6_ping_is_enabled_help') ?></small>
    </div>

    <div class="form-group">
        <label for="monitors_ping_method"><?= l('admin_settings.monitors_heartbeats.monitors_ping_method') ?></label>
        <select id="monitors_ping_method" name="monitors_ping_method" class="custom-select">
            <option value="exec" <?= settings()->monitors_heartbeats->monitors_ping_method == 'exec' ? 'selected="selected"' : null ?>>exec</option>
            <option value="fsockopen" <?= settings()->monitors_heartbeats->monitors_ping_method == 'fsockopen' ? 'selected="selected"' : null ?>>fsockopen</option>
        </select>
        <small class="form-text text-muted"><?= l('admin_settings.monitors_heartbeats.monitors_ping_method_help') ?></small>
    </div>

    <div class="form-group custom-control custom-switch">
        <input id="monitors_double_check_is_enabled" name="monitors_double_check_is_enabled" type="checkbox" class="custom-control-input" <?= settings()->monitors_heartbeats->monitors_double_check_is_enabled ? 'checked="checked"' : null?>>
        <label class="custom-control-label" for="monitors_double_check_is_enabled"><?= l('admin_settings.monitors_heartbeats.monitors_double_check_is_enabled') ?></label>
        <small class="form-text text-muted"><?= l('admin_settings.monitors_heartbeats.monitors_double_check_is_enabled_help') ?></small>
    </div>

    <div class="form-group">
        <label for="monitors_double_check_wait"><?= l('admin_settings.monitors_heartbeats.monitors_double_check_wait') ?></label>
        <div class="input-group">
            <input id="monitors_double_check_wait" type="number" min="0" max="5" name="monitors_double_check_wait" class="form-control" value="<?= settings()->monitors_heartbeats->monitors_double_check_wait ?>" />
            <div class="input-group-append">
                <span class="input-group-text"><?= l('global.date.seconds') ?></span>
            </div>
        </div>
        <small class="form-text text-muted"><?= l('admin_settings.monitors_heartbeats.monitors_double_check_wait_help') ?></small>
    </div>

    <div class="form-group">
        <label for="user_agent"><?= l('admin_settings.monitors_heartbeats.user_agent') ?></label>
        <input id="user_agent" type="text" name="user_agent" class="form-control" value="<?= settings()->monitors_heartbeats->user_agent ?>" />
        <small class="form-text text-muted"><?= l('admin_settings.monitors_heartbeats.user_agent_help') ?></small>
    </div>

    <div class="form-group">
        <label for="decimals"><?= l('admin_settings.monitors_heartbeats.decimals') ?></label>
        <input id="decimals" type="number" min="0" max="5" name="decimals" class="form-control" value="<?= settings()->monitors_heartbeats->decimals ?>" />
    </div>

    <div class="form-group">
        <label for="monitors_default_request_method"><?= l('admin_settings.monitors_heartbeats.monitors_default_request_method') ?></label>
        <select id="monitors_default_request_method" name="monitors_default_request_method" class="custom-select">
            <option value="GET" <?= settings()->monitors_heartbeats->monitors_default_request_method == 'GET' ? 'selected="selected"' : null ?>>GET</option>
            <option value="POST" <?= settings()->monitors_heartbeats->monitors_default_request_method == 'POST' ? 'selected="selected"' : null ?>>POST</option>
            <option value="HEAD" <?= settings()->monitors_heartbeats->monitors_default_request_method == 'HEAD' ? 'selected="selected"' : null ?>>HEAD</option>
            <option value="OPTIONS" <?= settings()->monitors_heartbeats->monitors_default_request_method == 'OPTIONS' ? 'selected="selected"' : null ?>>OPTIONS</option>
            <option value="PUT" <?= settings()->monitors_heartbeats->monitors_default_request_method == 'PUT' ? 'selected="selected"' : null ?>>PUT</option>
            <option value="PATCH" <?= settings()->monitors_heartbeats->monitors_default_request_method == 'PATCH' ? 'selected="selected"' : null ?>>PATCH</option>

        </select>
        <small class="form-text text-muted"><?= l('admin_settings.monitors_heartbeats.monitors_default_request_method_help') ?></small>
    </div>

    <div class="form-group">
        <label for="blacklisted_domains"><?= l('admin_settings.monitors_heartbeats.blacklisted_domains') ?></label>
        <textarea id="blacklisted_domains" class="form-control" name="blacklisted_domains"><?= implode(',', settings()->status_pages->blacklisted_domains ?? []) ?></textarea>
        <small class="form-text text-muted"><?= l('admin_settings.monitors_heartbeats.blacklisted_domains_help') ?></small>
    </div>

    <div class="form-group custom-control custom-switch">
        <input id="projects_is_enabled" name="projects_is_enabled" type="checkbox" class="custom-control-input" <?= settings()->monitors_heartbeats->projects_is_enabled ? 'checked="checked"' : null?>>
        <label class="custom-control-label" for="projects_is_enabled"><?= l('admin_settings.monitors_heartbeats.projects_is_enabled') ?></label>
        <small class="form-text text-muted"><?= l('admin_settings.monitors_heartbeats.projects_is_enabled_help') ?></small>
    </div>
</div>

<button type="submit" name="submit" class="btn btn-lg btn-block btn-primary mt-4"><?= l('global.update') ?></button>
