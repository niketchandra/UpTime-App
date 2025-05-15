<?php defined('ALTUMCODE') || die() ?>

<?php if(settings()->main->breadcrumbs_is_enabled): ?>
<nav aria-label="breadcrumb">
    <ol class="custom-breadcrumbs small">
        <li>
            <a href="<?= url('admin/ping-servers') ?>"><?= l('admin_ping_servers.breadcrumb') ?></a><i class="fas fa-fw fa-angle-right"></i>
        </li>
        <li class="active" aria-current="page"><?= l('admin_ping_server_create.breadcrumb') ?></li>
    </ol>
</nav>
<?php endif ?>

<div class="mb-4">
    <h1 class="h3 mb-0 mr-1"><i class="fas fa-fw fa-xs fa-map-marked-alt text-primary-900 mr-2"></i> <?= l('admin_ping_server_create.header') ?></h1>
</div>

<div class="alert alert-info" role="alert">
    <?= l('admin_ping_server_create.subheader') ?>
</div>

<?= \Altum\Alerts::output_alerts() ?>

<div class="card <?= \Altum\Alerts::has_field_errors() ? 'border-danger' : null ?>">
    <div class="card-body">

        <form action="" method="post" role="form">
            <input type="hidden" name="token" value="<?= \Altum\Csrf::get() ?>" />

            <div class="form-group">
                <label for="name"><?= l('admin_ping_servers.main.name') ?></label>
                <input id="name" type="text" class="form-control <?= \Altum\Alerts::has_field_errors('name') ? 'is-invalid' : null ?>" name="name" value="" required="required" />
                <?= \Altum\Alerts::output_field_error('name') ?>
            </div>

            <div class="form-group">
                <label for="url"><?= l('admin_ping_servers.main.url') ?></label>
                <input id="url" type="url" class="form-control <?= \Altum\Alerts::has_field_errors('url') ? 'is-invalid' : null ?>" name="url" value="" placeholder="<?= l('admin_ping_servers.main.url_placeholder') ?>" required="required" />
                <?= \Altum\Alerts::output_field_error('url') ?>
            </div>

            <div class="form-group">
                <label for="country_code"><?= l('global.country') ?></label>
                <select id="country_code" name="country_code" class="custom-select">
                    <?php foreach(get_countries_array() as $country_code => $country_name): ?>
                        <option value="<?= $country_code ?>"><?= $country_name ?></option>
                    <?php endforeach ?>
                </select>
            </div>

            <div class="form-group">
                <label for="city_name"><?= l('global.city') ?></label>
                <input id="city_name" type="text" class="form-control <?= \Altum\Alerts::has_field_errors('city_name') ? 'is-invalid' : null ?>" name="city_name" value="" required="required" />
                <?= \Altum\Alerts::output_field_error('city_name') ?>
                <small class="form-text text-muted"><?= l('admin_ping_servers.main.city_name_help') ?></small>
            </div>

            <div class="form-group custom-control custom-switch">
                <input id="is_enabled" name="is_enabled" type="checkbox" class="custom-control-input">
                <label class="custom-control-label" for="is_enabled"><?= l('global.status') ?></label>
            </div>

            <button type="submit" name="submit" class="btn btn-lg btn-block btn-primary mt-4"><?= l('global.create') ?></button>
        </form>

    </div>
</div>
