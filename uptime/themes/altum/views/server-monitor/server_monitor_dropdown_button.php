<?php defined('ALTUMCODE') || die() ?>

<div class="dropdown">
    <button type="button" class="btn btn-link text-secondary dropdown-toggle dropdown-toggle-simple" data-toggle="dropdown" data-boundary="viewport">
        <i class="fas fa-fw fa-ellipsis-v"></i>
    </button>

    <div class="dropdown-menu dropdown-menu-right">
        <a class="dropdown-item" href="<?= url('server-monitor/' . $data->id) ?>"><i class="fas fa-fw fa-sm fa-server mr-2"></i> <?= l('global.view') ?></a>
        <a class="dropdown-item" href="<?= url('server-monitor-update/' . $data->id) ?>"><i class="fas fa-fw fa-sm fa-pencil-alt mr-2"></i> <?= l('global.edit') ?></a>
        <a href="#" data-toggle="modal" data-target="#server_monitor_duplicate_modal" data-server-monitor-id="<?= $data->id ?>" class="dropdown-item"><i class="fas fa-fw fa-sm fa-clone mr-2"></i> <?= l('global.duplicate') ?></a>
        <a href="#" data-toggle="modal" data-target="#server_monitor_delete_modal" data-server-monitor-id="<?= $data->id ?>" data-resource-name="<?= $data->resource_name ?>" class="dropdown-item"><i class="fas fa-fw fa-sm fa-trash-alt mr-2"></i> <?= l('global.delete') ?></a>
    </div>
</div>

<?php \Altum\Event::add_content(include_view(THEME_PATH . 'views/partials/universal_delete_modal_form.php', [
    'name' => 'server_monitor',
    'resource_id' => 'server_monitor_id',
    'has_dynamic_resource_name' => true,
    'path' => 'server-monitors/delete'
]), 'modals', 'server_monitor_delete_modal'); ?>

<?php \Altum\Event::add_content(include_view(THEME_PATH . 'views/partials/duplicate_modal.php', ['modal_id' => 'server_monitor_duplicate_modal', 'resource_id' => 'server_monitor_id', 'path' => 'server-monitors/duplicate']), 'modals', 'server_monitor_duplicate_modal'); ?>
