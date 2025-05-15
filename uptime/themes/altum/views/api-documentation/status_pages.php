<?php defined('ALTUMCODE') || die() ?>

<div class="container">
    <?php if(settings()->main->breadcrumbs_is_enabled): ?>
        <nav aria-label="breadcrumb">
            <ol class="custom-breadcrumbs small">
                <li><a href="<?= url() ?>"><?= l('index.breadcrumb') ?></a> <i class="fas fa-fw fa-angle-right"></i></li>
                <li><a href="<?= url('api-documentation') ?>"><?= l('api_documentation.breadcrumb') ?></a> <i class="fas fa-fw fa-angle-right"></i></li>
                <li class="active" aria-current="page"><?= l('status_pages.title') ?></li>
            </ol>
        </nav>
    <?php endif ?>

    <h1 class="h4 mb-4"><?= l('status_pages.title') ?></h1>

    <div class="accordion">
        <div class="card">
            <div class="card-header bg-white p-3 position-relative">
                <h3 class="h6 m-0">
                    <a href="#" class="stretched-link" data-toggle="collapse" data-target="#status_pages_read_all" aria-expanded="true" aria-controls="status_pages_read_all">
                        <?= l('api_documentation.read_all') ?>
                    </a>
                </h3>
            </div>

            <div id="status_pages_read_all" class="collapse">
                <div class="card-body">

                    <div class="form-group mb-4">
                        <label><?= l('api_documentation.endpoint') ?></label>
                        <div class="card bg-gray-100 border-0">
                            <div class="card-body">
                                <span class="badge badge-success mr-3">GET</span> <span class="text-muted"><?= SITE_URL ?>api/status-pages/</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-4">
                        <label><?= l('api_documentation.example') ?></label>
                        <div class="card bg-gray-100 border-0">
                            <div class="card-body">
                                curl --request GET \<br />
                                --url '<?= SITE_URL ?>api/status-pages/' \<br />
                                --header 'Authorization: Bearer <span class="text-primary">{api_key}</span>' \
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive table-custom-container mb-4">
                        <table class="table table-custom">
                            <thead>
                            <tr>
                                <th><?= l('api_documentation.parameters') ?></th>
                                <th><?= l('global.details') ?></th>
                                <th><?= l('global.description') ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td>page</td>
                                <td>
                                    <span class="badge badge-info"><i class="fas fa-fw fa-sm fa-circle-notch mr-1"></i> <?= l('api_documentation.optional') ?></span>
                                    <span class="badge badge-secondary"><i class="fas fa-fw fa-sm fa-hashtag mr-1"></i> <?= l('api_documentation.int') ?></span>
                                </td>
                                <td><?= l('api_documentation.filters.page') ?></td>
                            </tr>
                            <tr>
                                <td>results_per_page</td>
                                <td>
                                    <span class="badge badge-info"><i class="fas fa-fw fa-sm fa-circle-notch mr-1"></i> <?= l('api_documentation.optional') ?></span>
                                    <span class="badge badge-secondary"><i class="fas fa-fw fa-sm fa-hashtag mr-1"></i> <?= l('api_documentation.int') ?></span>
                                </td>
                                <td><?= sprintf(l('api_documentation.filters.results_per_page'), '<code>' . implode('</code> , <code>', [10, 25, 50, 100, 250, 500, 1000]) . '</code>', 25) ?></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="form-group">
                        <label><?= l('api_documentation.response') ?></label>
                        <div data-shiki="json">
                            {
                            "data": [
                            {
                            "id": 1,
                            "domain_id": 0,
                            "monitors_ids": [1,2,3],
                            "project_id": 0,
                            "url": "example",
                            "full_url": "<?= SITE_URL ?>s/example/",
                            "name": "Example",
                            "description": "This is just a simple description for the example status page 👋.",
                            "socials": {
                            "facebook": "example",
                            "instagram": "example",
                            "twitter": "example",
                            "email": "",
                            "website": "https://example.com/"
                            },
                            "logo_url": "",
                            "favicon_url": ""
                            "password": false,
                            "timezone": "UTC",
                            "custom_js": "",
                            "custom_css": "",
                            "pageviews": 50,
                            "is_se_visible": true,
                            "is_removed_branding": false,
                            "is_enabled": true,
                            "datetime": "<?= get_date() ?>"
                            }
                            ],
                            "meta": {
                            "page": 1,
                            "results_per_page": 25,
                            "total": 1,
                            "total_pages": 1
                            },
                            "links": {
                            "first": "<?= SITE_URL ?>api/status-pages?&page=1",
                            "last": "<?= SITE_URL ?>api/status-pages?&page=1",
                            "next": null,
                            "prev": null,
                            "self": "<?= SITE_URL ?>api/status-pages?&page=1"
                            }
                            }
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white p-3 position-relative">
                <h3 class="h6 m-0">
                    <a href="#" class="stretched-link" data-toggle="collapse" data-target="#status_pages_read" aria-expanded="true" aria-controls="status_pages_read">
                        <?= l('api_documentation.read') ?>
                    </a>
                </h3>
            </div>

            <div id="status_pages_read" class="collapse">
                <div class="card-body">

                    <div class="form-group mb-4">
                        <label><?= l('api_documentation.endpoint') ?></label>
                        <div class="card bg-gray-100 border-0">
                            <div class="card-body">
                                <span class="badge badge-success mr-3">GET</span> <span class="text-muted"><?= SITE_URL ?>api/status-pages/</span><span class="text-primary">{status_page_id}</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-4">
                        <label><?= l('api_documentation.example') ?></label>
                        <div class="card bg-gray-100 border-0">
                            <div class="card-body">
                                curl --request GET \<br />
                                --url '<?= SITE_URL ?>api/status-pages/<span class="text-primary">{status_page_id}</span>' \<br />
                                --header 'Authorization: Bearer <span class="text-primary">{api_key}</span>' \
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><?= l('api_documentation.response') ?></label>
                        <div data-shiki="json">
                            {
                            "data": {
                            "id": 1,
                            "domain_id": 0,
                            "monitors_ids": [1,2,3],
                            "project_id": 0,
                            "url": "example",
                            "full_url": "<?= SITE_URL ?>s/example/",
                            "name": "Example",
                            "description": "This is just a simple description for the example status page 👋.",
                            "socials": {
                            "facebook": "example",
                            "instagram": "example",
                            "twitter": "example",
                            "email": "",
                            "website": "https://example.com/"
                            },
                            "logo_url": "",
                            "favicon_url": ""
                            "password": false,
                            "timezone": "UTC",
                            "custom_js": "",
                            "custom_css": "",
                            "pageviews": 50,
                            "is_se_visible": true,
                            "is_removed_branding": false,
                            "is_enabled": true,
                            "datetime": "<?= get_date() ?>"
                            }
                            }
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require THEME_PATH . 'views/partials/shiki_highlighter.php' ?>
