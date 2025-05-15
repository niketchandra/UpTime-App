<?php defined('ALTUMCODE') || die() ?>

<div class="modal fade" id="incident_comment_modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">

            <div class="modal-body">
                <div class="d-flex justify-content-between mb-3">
                    <h5 class="modal-title">
                        <i class="fas fa-fw fa-sm fa-comment text-dark mr-2"></i>
                        <?= l('incident_comment_modal.header') ?>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" title="<?= l('global.close') ?>">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <form id="incident_comment_modal_form" name="incident_comment_modal_form" method="post" action="" role="form">
                    <input type="hidden" name="token" value="<?= \Altum\Csrf::get() ?>" required="required" />
                    <input type="hidden" name="incident_id" value="" required="required" />

                    <div class="notification-container"></div>

                    <div class="form-group">
                        <label for="comment"><?= l('incidents.comment') ?></label>
                        <textarea id="comment" name="comment" class="form-control"></textarea>
                    </div>

                    <div class="mt-4">
                        <button type="submit" name="submit" class="btn btn-block btn-primary" data-is-ajax><?= l('global.update') ?></button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<?php ob_start() ?>
<script>
    'use strict';

    /* Modal opening */
    $('#incident_comment_modal').on('show.bs.modal', event => {
        let incident_id = event.relatedTarget.getAttribute('data-incident-id');
        let comment = event.relatedTarget.getAttribute('data-comment');

        document.querySelector('#comment').value = comment;
        document.querySelector('[name="incident_id"]').value = incident_id;
    });

    /* On form submission */
    document.querySelector('#incident_comment_modal_form').addEventListener('submit', async event => {
        event.preventDefault();

        pause_submit_button(document.querySelector('#incident_comment_modal_form'));

        /* Notification container */
        let notification_container = event.currentTarget.querySelector('.notification-container');
        notification_container.innerHTML = '';

        /* Prepare form data */
        let form = new FormData(document.querySelector('#incident_comment_modal_form'));

        /* Send request to server */
        let response = await fetch(`${url}incidents/update_ajax`, {
            method: 'post',
            body: form
        });

        let data = null;
        try {
            data = await response.json();
        } catch (error) {
            display_notifications(<?= json_encode(l('global.error_message.basic')) ?>, 'error', notification_container);
        }

        if(!response.ok) {
            display_notifications(<?= json_encode(l('global.error_message.basic')) ?>, 'error', notification_container);
        }

        if(data && data.status) {
            display_notifications(data.message, data.status, notification_container);
            document.querySelector(`#incident_id_${data.details.incident_id}`).innerHTML = data.details.comment;
            document.querySelector(`[data-incident-id="${data.details.incident_id}"]`).dataset.comment = data.details.comment;
        }

        notification_container.scrollIntoView({ behavior: 'smooth', block: 'center' });
        enable_submit_button(document.querySelector('#incident_comment_modal_form'));

        setTimeout(() => {
            /* Close modal */
            $('#incident_comment_modal').modal('hide');

            /* Remove notification */
            notification_container.innerHTML = '';
        }, 1500);
    });
</script>
<?php \Altum\Event::add_content(ob_get_clean(), 'javascript') ?>
