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


file_put_contents($CFG->dataroot . '/payment.log', date("Y-m-d H:i:s") . "\n" .
serialize($_REQUEST) . "\n\n", FILE_APPEND | LOCK_EX);


$invid     = required_param('label', PARAM_TEXT);
$amount    = required_param('amount', PARAM_TEXT); // TEXT only!
$signature = required_param('sha1_hash', PARAM_ALPHANUMEXT);

$opid = required_param('operation_id', PARAM_TEXT);
$dt   = required_param('datetime', PARAM_TEXT);
$sdr  = required_param('sender', PARAM_INT);


if (empty($invid)) {
    throw new Error('FAIL. Empty transaction id.');
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

if ($config->savedebugdata) {
    file_put_contents($CFG->dataroot . '/payment.log', date("Y-m-d H:i:s") . "\n" .
    serialize($_REQUEST) . "\n\n", FILE_APPEND | LOCK_EX);
}

// Check crc.
$nt = "p2p-incoming";
$secret = $config->secret;

$crc = hash('sha1', "$nt&$opid&$amount&643&$dt&$sdr&false&$secret&$invid");


file_put_contents($CFG->dataroot . '/payment.log', date("Y-m-d H:i:s") . "\n" .
serialize("$nt&$opid&$amount&643&$dt&$sdr&false&$secret&$invid") . "\n\n", FILE_APPEND | LOCK_EX);


if ($signature !== $crc) {
    throw new Error('FAIL. Signature does not match.');
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
if (!$DB->update_record('paygw_yoomoney', $yoomoneytx)) {
    throw new Error('FAIL. Update db error.');
} else {
    die('OK');
}
