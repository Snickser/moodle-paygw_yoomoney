<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Contains class for yoomoney payment gateway.
 *
 * @package    paygw_yoomoney
 * @copyright  2024 Alex Orlov <snickser@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_yoomoney;

/**
 * The gateway class for yoomoney payment gateway.
 *
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gateway extends \core_payment\gateway {
    /**
     * Configuration form for currency
     */
    public static function get_supported_currencies(): array {
        // 3-character ISO-4217: https://en.wikipedia.org/wiki/ISO_4217#Active_codes.
        return [
            'RUB',
        ];
    }

    /**
     * Configuration form for the gateway instance
     *
     * Use $form->get_mform() to access the \MoodleQuickForm instance
     *
     * @param \core_payment\form\account_gateway $form
     */
    public static function add_configuration_to_gateway_form(\core_payment\form\account_gateway $form): void {
        $mform = $form->get_mform();

        $mform->addElement('text', 'wallet', get_string('wallet', 'paygw_yoomoney'), ['size' => 20]);
        $mform->setType('wallet', PARAM_TEXT);
        $mform->addRule('wallet', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'notify_secret', get_string('notify_secret', 'paygw_yoomoney'), ['size' => 30]);
        $mform->setType('notify_secret', PARAM_TEXT);
        $mform->addRule('notify_secret', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'client_id', get_string('client_id', 'paygw_yoomoney'), ['size' => 50]);
        $mform->setType('client_id', PARAM_TEXT);
        $mform->addRule('client_id', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'client_secret', get_string('client_secret', 'paygw_yoomoney'), ['size' => 50]);
        $mform->setType('client_secret', PARAM_TEXT);
        $mform->addRule('client_secret', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'token', get_string('token', 'paygw_yoomoney'), ['size' => 50]);
        $mform->setType('token', PARAM_TEXT);
        $mform->disabledIf('token', 'wallet');

        $sesskey = sesskey();
        $options = '<a href="/payment/gateway/yoomoney/oauth2callback.php?sesskey=' . $sesskey .
        '&id=' . $form->get_gateway_persistent()->get('id') . '">' . get_string('gettoken', 'paygw_yoomoney') . '</a>';
        $mform->addElement('static', 'auth', null, $options);

        $options = [
         'AC' => get_string('plastic', 'paygw_yoomoney'),
         'PC' => get_string('wallet', 'paygw_yoomoney'),
        ];
        $mform->addElement(
            'select',
            'paymentmethod',
            get_string('paymentmethod', 'paygw_yoomoney'),
            $options,
        );
        $mform->setType('paymentmethod', PARAM_TEXT);
        $mform->addHelpButton('paymentmethod', 'paymentmethod', 'paygw_yoomoney');

        $mform->addElement('static');

        $mform->addElement(
            'advcheckbox',
            'skipmode',
            get_string('skipmode', 'paygw_yoomoney'),
            get_string('skipmode', 'paygw_yoomoney')
        );
        $mform->setType('skipmode', PARAM_INT);
        $mform->addHelpButton('skipmode', 'skipmode', 'paygw_yoomoney');

        $mform->addElement(
            'advcheckbox',
            'passwordmode',
            get_string('passwordmode', 'paygw_yoomoney'),
            get_string('passwordmode', 'paygw_yoomoney')
        );
        $mform->setType('passwordmode', PARAM_INT);
        $mform->disabledIf('passwordmode', 'skipmode', "neq", 0);

        $mform->addElement('text', 'password', get_string('password', 'paygw_yoomoney'), ['size' => 20]);
        $mform->setType('password', PARAM_TEXT);
        $mform->disabledIf('password', 'passwordmode');
        $mform->disabledIf('password', 'skipmode', "neq", 0);
        $mform->addHelpButton('password', 'password', 'paygw_yoomoney');

        $mform->addElement(
            'advcheckbox',
            'usedetails',
            get_string('usedetails', 'paygw_yoomoney'),
            get_string('usedetails', 'paygw_yoomoney')
        );
        $mform->setType('usedetails', PARAM_INT);
        $mform->addHelpButton('usedetails', 'usedetails', 'paygw_yoomoney');

        $mform->addElement(
            'advcheckbox',
            'showduration',
            get_string('showduration', 'paygw_yoomoney'),
            get_string('showduration', 'paygw_yoomoney')
        );
        $mform->setType('showduration', PARAM_INT);

        $mform->addElement(
            'advcheckbox',
            'fixcost',
            get_string('fixcost', 'paygw_yoomoney'),
            get_string('fixcost', 'paygw_yoomoney')
        );
        $mform->setType('fixcost', PARAM_INT);
        $mform->addHelpButton('fixcost', 'fixcost', 'paygw_yoomoney');

        $mform->addElement('text', 'suggest', get_string('suggest', 'paygw_yoomoney'), ['size' => 10]);
        $mform->setType('suggest', PARAM_TEXT);
        $mform->disabledIf('suggest', 'fixcost', "neq", 0);

        $mform->addElement('text', 'maxcost', get_string('maxcost', 'paygw_yoomoney'), ['size' => 10]);
        $mform->setType('maxcost', PARAM_TEXT);
        $mform->disabledIf('maxcost', 'fixcost', "neq", 0);

        global $CFG;
        $mform->addElement('html', '<div class="label-callback" style="background: pink; padding: 15px;">');
        $mform->addElement('html', get_string('redirect_uri', 'paygw_yoomoney') . '<br>');
        $mform->addElement('html', $CFG->wwwroot . '/payment/gateway/yoomoney/oauth2callback.php<br>');
        $mform->addElement('html', get_string('callback_url', 'paygw_yoomoney') . '<br>');
        $mform->addElement('html', $CFG->wwwroot . '/payment/gateway/yoomoney/callback.php<br>');
        $mform->addElement('html', get_string('callback_help', 'paygw_yoomoney') . '</div><br>');

        $plugininfo = \core_plugin_manager::instance()->get_plugin_info('paygw_yoomoney');
        $donate = get_string('donate', 'paygw_yoomoney', $plugininfo);
        $mform->addElement('html', $donate);
    }

    /**
     * Validates the gateway configuration form.
     *
     * @param \core_payment\form\account_gateway $form
     * @param \stdClass $data
     * @param array $files
     * @param array $errors form errors (passed by reference)
     */
    public static function validate_gateway_form(
        \core_payment\form\account_gateway $form,
        \stdClass $data,
        array $files,
        array &$errors
    ): void {
        if ($data->enabled && empty($data->wallet)) {
            $errors['enabled'] = get_string('gatewaycannotbeenabled', 'payment');
        }
        if ($data->maxcost && $data->maxcost < $data->suggest) {
            $errors['maxcost'] = get_string('maxcosterror', 'paygw_yoomoney');
        }
    }
}
