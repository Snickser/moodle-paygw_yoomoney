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
 * @package     paygw_yookassa
 * @category    admin
 * @copyright   2024 Alex Orlov <snickser@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_payment\helper;

require_once(__DIR__ . '/../../../config.php');

require_once($CFG->libdir . '/filelib.php');

global $CFG, $USER, $DB;

defined('MOODLE_INTERNAL') || die();

require_login();
require_sesskey();

$id = optional_param('id', 0, PARAM_INT);
$accountid = optional_param('accountid', 0, PARAM_INT);
$gatewayname = optional_param('gateway', null, PARAM_COMPONENT);

if ($id) {
    $gateway = new \core_payment\account_gateway($id);
    $account = new \core_payment\account($gateway->get('accountid'));
} else if ($accountid) {
    $account = new \core_payment\account($accountid);
    $gateway = $account->get_gateways()[$gatewayname] ?? null;
}

if (empty($account) || empty($gateway)) {
    throw new moodle_exception('gatewaynotfound', 'payment');
}
require_capability('moodle/payment:manageaccounts', $account->get_context());

$config = json_decode($gateway->get('config'));

if($config->maxcost > 0){
    $maxcost = $config->maxcost;
} else {
    $maxcost = 0;
}

// Get token.
$location = 'https://yoomoney.ru/oauth/authorize';

$data = "client_id=$config->client_id&response_type=code" .
"&redirect_uri=" . urlencode($CFG->wwwroot . "/admin/oauth2callback.php") .
"&scope=payment.to-account(\"$config->wallet\").limit(,$maxcost) money-source(\"wallet\",\"card\")" .
"&instance_name=$id".
"&client_secret=$config->client_secret"
;

$options = [
    'CURLOPT_RETURNTRANSFER' => true,
    'CURLOPT_TIMEOUT' => 30,
    'CURLOPT_FOLLOWLOCATION' => false,
    'CURLOPT_HTTP_VERSION' => CURL_HTTP_VERSION_1_1,
    'CURLOPT_SSLVERSION' => CURL_SSLVERSION_TLSv1_2,
    'CURLOPT_HTTPHEADER' => [
        'Content-Type: application/x-www-form-urlencoded',
    ],
];
$curl = new curl();
$response = $curl->post($location, $data, $options);

if(!empty($curl->get_info()['redirect_url'])){
    redirect($curl->get_info()['redirect_url']) ;
} else {
    redirect($curl->get_info()['url']) ;
}
