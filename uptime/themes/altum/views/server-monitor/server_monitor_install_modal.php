<?php defined('ALTUMCODE') || die() ?>

<div class="modal fade" id="server_monitor_install_modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">

            <div class="modal-body">
                <div class="d-flex justify-content-between mb-3">
                    <h5 class="modal-title">
                        <i class="fas fa-fw fa-sm fa-code text-dark mr-2"></i>
                        <?= l('server_monitor_install_modal.header') ?>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" title="<?= l('global.close') ?>">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="tab-content">
                    <p class="text-muted"><?= l('server_monitor_install_modal.subheader') ?></p>

                    <pre id="install_code" class="pre-custom rounded"></pre>

                    <div class="mt-4">
                        <button type="button" class="btn btn-block btn-primary" data-clipboard-target="#install_code" data-copied="<?= l('global.clipboard_copied') ?>"><?= l('global.clipboard_copy') ?></button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php ob_start() ?>
<script>
    /* On modal show */
    $('#server_monitor_install_modal').on('show.bs.modal', event => {
        let server_monitor_id = $(event.relatedTarget).data('server-monitor-id');
        let api_key = $(event.relatedTarget).data('api-key');
        let server_monitor_check_interval_seconds = $(event.relatedTarget).data('server-check-interval-seconds');
        let name = $(event.relatedTarget).data('name');
        let site_title = <?= json_encode(get_slug(settings()->main->title)) ?>;
        let cron = '';

        switch(server_monitor_check_interval_seconds) {
            case 60:
                cron = '* * * * *';
                break;
            default:
                cron = `*/${server_monitor_check_interval_seconds / 60} * * * *`;
                break;
        }

        let install_html = `script_name="${site_title}.sh" && \
wget -O "$PWD/$script_name" "${site_url}server-monitor-code/${server_monitor_id}/${api_key}" && \
chmod +x "$PWD/$script_name" && \
(crontab -l 2>/dev/null | grep -v "$script_name"; echo "${cron} $PWD/$script_name") | crontab - && \
echo "The ${name} monitoring script from ${site_title} (${site_url}) has been installed."`;

        $(event.currentTarget).find('pre').html(install_html);

        new ClipboardJS('[data-clipboard-target]');

        /* Handle on click button */
        let copy_button = $(event.currentTarget).find('[data-clipboard-target]');
        let initial_text = copy_button.text();

        copy_button.on('click', () => {
            copy_button.text(copy_button.data('copied'));

            setTimeout(() => {
                copy_button.text(initial_text);
            }, 2500);
        });
    });
</script>
<?php \Altum\Event::add_content(ob_get_clean(), 'javascript') ?>
