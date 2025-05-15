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

namespace Altum\Controllers;

use Altum\Response;

defined('ALTUMCODE') || die();

class Incidents extends Controller {

    public function index() {
        redirect('not-found');
    }

    public function update_ajax () {

        if(empty($_POST)) {
            redirect();
        }

        \Altum\Authentication::guard();

        /* Team checks */
        if(\Altum\Teams::is_delegated() && !\Altum\Teams::has_access('update.monitors')) {
            Response::json(l('global.info_message.team_no_access'), 'error');
        }

        $_POST['comment'] = input_clean($_POST['comment'], 512);
        $_POST['incident_id'] = (int) $_POST['incident_id'];

        /* Check for any errors */
        $required_fields = ['incident_id'];
        foreach($required_fields as $field) {
            if(!isset($_POST[$field]) || (isset($_POST[$field]) && empty($_POST[$field]) && $_POST[$field] != '0')) {
                Response::json(l('global.error_message.empty_fields'), 'error');
            }
        }

        if(!\Altum\Csrf::check('token')) {
            Response::json(l('global.error_message.invalid_csrf_token'), 'error');
        }

        /* Database query */
        db()->where('user_id', $this->user->user_id)->where('incident_id', $_POST['incident_id'])->update('incidents', [
            'comment' => $_POST['comment'],
        ]);

        /* Set a nice success message */
        Response::json(l('global.success_message.update2'), 'success', ['comment' => $_POST['comment'], 'incident_id' => $_POST['incident_id']]);

    }

}
