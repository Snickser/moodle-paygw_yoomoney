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
 * Redirects user to the original page
 *
 * @package   paygw_yoomoney
 * @copyright 2024 Alex Orlov <snickser@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_payment\helper;

require("../../../config.php");
global $CFG, $USER, $DB;

defined('MOODLE_INTERNAL') || die();

require_login();

$id = required_param('ID', PARAM_INT);

if (!$yoomoneytx = $DB->get_record('paygw_yoomoney', ['paymentid' => $id])) {
    throw new \moodle_exception(get_string('error_notvalidtxid', 'paygw_yoomoney'), 'paygw_yoomoney');
}

if (!$payment = $DB->get_record('payments', ['id' => $yoomoneytx->paymentid])) {
    throw new \moodle_exception(get_string('error_notvalidpayment', 'paygw_yoomoney'), 'paygw_yoomoney');
}

$paymentarea = $payment->paymentarea;
$component   = $payment->component;
$itemid      = $payment->itemid;

$url = helper::get_success_url($component, $paymentarea, $itemid);

if ($yoomoneytx->success) {
    redirect($url, get_string('payment_success', 'paygw_yoomoney'), 0, 'success');
} else {
    redirect($url, get_string('payment_error', 'paygw_yoomoney'), 0, 'error');
}