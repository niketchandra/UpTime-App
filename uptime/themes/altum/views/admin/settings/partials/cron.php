<?php defined('ALTUMCODE') || die() ?>

<?php foreach(['reset', 'monitors', 'heartbeats', 'domain_names', 'dns_monitors', 'monitors_email_reports', 'heartbeats_email_reports', 'broadcasts'] as $cron): ?>
    <div class="form-group">
        <label for="cron_<?= $cron ?>"><?= l('admin_settings.cron.' . $cron) ?></label>
        <div class="input-group">
            <input id="cron_<?= $cron ?>" name="cron_<?= $cron ?>" type="text" class="form-control" value="<?= '* * * * * wget --quiet -O /dev/null ' . SITE_URL . 'cron/' . $cron . '?key=' . settings()->cron->key ?>" readonly="readonly" />
            <div class="input-group-append">
                <button
                        type="button"
                        class="btn btn-light"
                        data-toggle="tooltip"
                        title="<?= l('global.clipboard_copy') ?>"
                        aria-label="<?= l('global.clipboard_copy') ?>"
                        data-copy="<?= l('global.clipboard_copy') ?>"
                        data-copied="<?= l('global.clipboard_copied') ?>"
                        data-clipboard-text="<?= '* * * * * wget --quiet -O /dev/null ' . SITE_URL . 'cron/' . $cron . '?key=' . settings()->cron->key ?>"
                >
                    <i class="fas fa-fw fa-sm fa-copy"></i>
                </button>
            </div>
            <div class="input-group-append">
                <a
                        href="<?= SITE_URL . 'cron/' . $cron . '?key=' . settings()->cron->key ?>"
                        target="_blank"
                        class="btn btn-light"
                        data-toggle="tooltip"
                        title="<?= l('admin_settings.cron.run_manually') ?>"
                >
                    <i class="fas fa-fw fa-sm fa-external-link-alt"></i>
                </a>
            </div>
        </div>
        <?php
        $text_class = 'text-muted';

        if(!isset(settings()->cron->{$cron . '_datetime'})) {
            $text_class = 'text-danger';
        } else {

            if((new DateTime(settings()->cron->{$cron . '_datetime'})) < (new \DateTime())->modify('-1 hour')) {
                $text_class = 'text-warning';
            }

            if((new DateTime(settings()->cron->{$cron . '_datetime'})) < (new \DateTime())->modify('-1 day')) {
                $text_class = 'text-danger';
            }
        }
        ?>

        <small class="form-text <?= $text_class ?>"><?= sprintf(l('admin_settings.cron.last_execution'), isset(settings()->cron->{$cron . '_datetime'}) ? \Altum\Date::get_timeago(settings()->cron->{$cron . '_datetime'}) : '-') ?></small>
    </div>
<?php endforeach ?>

<div <?= !\Altum\Plugin::is_active('push-notifications') ? 'data-toggle="tooltip" title="' . sprintf(l('admin_plugins.no_access'), \Altum\Plugin::get('push-notifications')->name ?? 'push-notifications') . '"' : null ?>>
    <div class="<?= !\Altum\Plugin::is_active('push-notifications') ? 'container-disabled' : null ?>">
        <div class="form-group">
            <label for="cron_push_notifications"><?= l('admin_settings.cron.push_notifications') ?></label>
            <div class="input-group">
                <input id="cron_push_notifications" name="cron_push_notifications" type="text" class="form-control" value="<?= '* * * * * wget --quiet -O /dev/null ' . SITE_URL . 'cron/push_notifications?key=' . settings()->cron->key ?>" readonly="readonly" />
                <div class="input-group-append">
                    <button
                            type="button"
                            class="btn btn-light"
                            data-toggle="tooltip"
                            title="<?= l('global.clipboard_copy') ?>"
                            aria-label="<?= l('global.clipboard_copy') ?>"
                            data-copy="<?= l('global.clipboard_copy') ?>"
                            data-copied="<?= l('global.clipboard_copied') ?>"
                            data-clipboard-text="<?= '* * * * * wget --quiet -O /dev/null ' . SITE_URL . 'cron/push_notifications?key=' . settings()->cron->key ?>"
                    >
                        <i class="fas fa-fw fa-sm fa-copy"></i>
                    </button>
                </div>
                <div class="input-group-append">
                    <a
                            href="<?= SITE_URL . 'cron/push_notifications?key=' . settings()->cron->key ?>"
                            target="_blank"
                            class="btn btn-light"
                            data-toggle="tooltip"
                            title="<?= l('admin_settings.cron.run_manually') ?>"
                    >
                        <i class="fas fa-fw fa-sm fa-external-link-alt"></i>
                    </a>
                </div>
            </div>
            <?php
            $text_class = 'text-muted';

            if(!isset(settings()->cron->push_notifications_datetime)) {
                $text_class = 'text-danger';
            } else {

                if((new DateTime(settings()->cron->push_notifications_datetime)) < (new \DateTime())->modify('-1 hour')) {
                    $text_class = 'text-warning';
                }

                if((new DateTime(settings()->cron->push_notifications_datetime)) < (new \DateTime())->modify('-1 day')) {
                    $text_class = 'text-danger';
                }
            }
            ?>
            
            <small class="form-text <?= $text_class ?>"><?= sprintf(l('admin_settings.cron.last_execution'), isset(settings()->cron->push_notifications_datetime) ? \Altum\Date::get_timeago(settings()->cron->push_notifications_datetime) : '-') ?></small>
        </div>
    </div>
</div>

<hr class="border-gray-200 my-5" />

<h5>BETA</h5>
<p class="text-muted">
    This is a beta functionality that you can run only via <code>CLI mode</code> & if your host supports <code>pcntl</code> extension.
    <br /> This is intended to run checks in parallel so that a Cron job run is faster and check more monitors in less time.
</p>

<div class="form-group">
    <label for="cron_monitors"><?= l('admin_settings.cron.monitors') ?></label>
    <input id="cron_monitors" name="cron_monitors" type="text" class="form-control" value="<?= '* * * * * php -q ' . ROOT_PATH . 'index.php ' . 'cron/monitors ' . 'key=' . settings()->cron->key . ' >/dev/null 2>&1' ?>" readonly="readonly" />
    <small class="form-text text-muted"><?= sprintf(l('admin_settings.cron.last_execution'), isset(settings()->cron->monitors_datetime) ? \Altum\Date::get_timeago(settings()->cron->monitors_datetime) : '-') ?></small>
</div>

<?php include_view(THEME_PATH . 'views/partials/clipboard_js.php') ?>
