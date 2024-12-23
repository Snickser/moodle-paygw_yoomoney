<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin administration pages are defined here.
 *
 * @package     paygw_yoomoney
 * @copyright   2024 Alex Orlov <snickser@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_payment\helper;
use paygw_yoomoney\notifications;

require("../../../config.php");
global $CFG, $USER, $DB;
require_once($CFG->libdir . '/filelib.php');

defined('MOODLE_INTERNAL') || die();

// Set the context of the page.
$PAGE->set_context(context_system::instance());

$invid     = required_param('label', PARAM_TEXT); // TEXT only!
$amount    = required_param('amount', PARAM_TEXT); // TEXT only!
$signature = required_param('sha1_hash', PARAM_ALPHANUMEXT);

$unaccepted = required_param('unaccepted', PARAM_TEXT);

$nt   = required_param('notification_type', PARAM_TEXT);
$opid = required_param('operation_id', PARAM_TEXT);
$dt   = required_param('datetime', PARAM_TEXT);
$sdr  = required_param('sender', PARAM_TEXT);

if (empty($invid)) {
    throw new Error('FAIL. Empty transaction id.');
}

if ($unaccepted == 'true') {
    throw new Error('FAIL. Unaccepted payment.');
}

if (!$yoomoneytx = $DB->get_record('paygw_yoomoney', ['paymentid' => $invid])) {
    throw new Error('FAIL. Not a valid transaction id.');
}

if (!$payment = $DB->get_record('payments', ['id' => $yoomoneytx->paymentid])) {
    throw new Error('FAIL. Not a valid payment.');
}
$component   = $payment->component;
$paymentarea = $payment->paymentarea;
$itemid      = $payment->itemid;
$paymentid   = $payment->id;
$userid      = $payment->userid;

// Get config.
$config = (object) helper::get_gateway_configuration($component, $paymentarea, $itemid, 'yoomoney');

// Check crc.
$secret = $config->notify_secret;

$crc = hash('sha1', "$nt&$opid&$amount&643&$dt&$sdr&false&$secret&$invid");

if ($signature !== $crc) {
    throw new Error('FAIL. Signature does not match.');
}

$data = "operation_id=$opid";
$location = 'https://yoomoney.ru/api/operation-details';
$options = [
    'CURLOPT_RETURNTRANSFER' => true,
    'CURLOPT_TIMEOUT' => 30,
    'CURLOPT_HTTP_VERSION' => CURL_HTTP_VERSION_1_1,
    'CURLOPT_SSLVERSION' => CURL_SSLVERSION_TLSv1_2,
    'CURLOPT_HTTPHEADER' => [
        'Authorization: Bearer ' . $config->token,
        'Content-Type: application/x-www-form-urlencoded',
    ],
    'CURLOPT_FOLLOWLOCATION' => false,
];
$curl = new curl();
$jsonresponse = $curl->post($location, $data, $options);
$response = json_decode($jsonresponse);

if ($config->savedebugdata) {
    file_put_contents($CFG->dataroot . '/payment.log', date("Y-m-d H:i:s") . "\n" .
    serialize($response) . "\n\n", FILE_APPEND | LOCK_EX);
}

if ($response->status !== "success") {
    throw new Error('FAIL. Payment status error.');
}

// Update payment.
$payment->timemodified = time();
$DB->update_record('payments', $payment);

// Deliver order.
helper::deliver_order($component, $paymentarea, $itemid, $paymentid, $userid);

// Notify user.
notifications::notify(
    $userid,
    $payment->amount,
    $payment->currency,
    $paymentid,
    'Success completed'
);

// Update paygw.
$yoomoneytx->success = 1;
if (!$DB->update_record('paygw_yoomoney', $yoomoneytx)) {
    throw new Error('FAIL. Update db error.');
} else {
    die('OK');
}
