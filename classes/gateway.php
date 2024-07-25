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

        $mform->addElement(
            'advcheckbox',
            'savedebugdata',
            get_string('savedebugdata', 'paygw_yoomoney'),
            get_string('savedebugdata', 'paygw_yoomoney')
        );

        $mform->setType('savedebugdata', PARAM_INT);
        $mform->addHelpButton('savedebugdata', 'savedebugdata', 'paygw_yoomoney');

        $mform->addElement('text', 'fixdesc', get_string('fixdesc', 'paygw_yoomoney'), ['size' => 50]);
        $mform->setType('fixdesc', PARAM_TEXT);
        $mform->addRule('fixdesc', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('fixdesc', 'fixdesc', 'paygw_yoomoney');

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
        $mform->addElement('html', '<div class="label-callback" style="background: pink; padding: 15px;">' .
                                    get_string('callback_url', 'paygw_yoomoney') . '<br>');
        $mform->addElement('html', $CFG->wwwroot . '/payment/gateway/yoomoney/callback.php<br>');
        $mform->addElement('html', get_string('callback_help', 'paygw_yoomoney') . '</div><br>');

        $header = '<div>Новые версии плагина вы можете найти на
 <a href=https://github.com/Snickser/moodle-paygw_yoomoney>GitHub.com</a><br>
 Пожалуйста, отправьте мне немножко <a href="https://yoomoney.ru/fundraise/143H2JO3LLE.240720">доната</a>😊</div>
 <iframe src="https://yoomoney.ru/quickpay/fundraise/button?billNumber=143H2JO3LLE.240720"
 width="330" height="50" frameborder="0" allowtransparency="true" scrolling="no"></iframe>';
        $mform->addElement('html', $header);
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
