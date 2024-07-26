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
 * @category    admin
 * @copyright   2024 Alex Orlov <snickser@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_payment\helper;

require_once(__DIR__ . '/../../../config.php');

require_once($CFG->libdir . '/filelib.php');

global $CFG, $DB;

defined('MOODLE_INTERNAL') || die();

require_login();
require_sesskey();

$id = optional_param('id', 0, PARAM_INT);
$code = optional_param('code', null, PARAM_TEXT);

if ($id) {
    $gateway = new \core_payment\account_gateway($id);
    $account = new \core_payment\account($gateway->get('accountid'));
}
if (empty($account) || empty($gateway)) {
    throw new moodle_exception('gatewaynotfound', 'payment');
}
require_capability('moodle/payment:manageaccounts', $account->get_context());

$config = json_decode($gateway->get('config'));

if (empty($code)) {
    // Get code.
    $location = 'https://yoomoney.ru/oauth/authorize';

    if ($config->maxcost > 0) {
        $maxcost = $config->maxcost;
    } else {
        $maxcost = 1;
    }

    $data = "client_id=$config->client_id&response_type=code" .
     "&redirect_uri=" . urlencode($CFG->wwwroot . "/payment/gateway/yoomoney/oauth2callback.php?id=$id&sesskey=" . sesskey()) .
/* "&scope=payment.to-account(\"$config->wallet\").limit(,$maxcost) money-source(\"wallet\",\"card\")" . */
     "&scope=operation-details" .
     "&instance_name=$id" .
     "&client_secret=$config->client_secret";

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

    if (!empty($curl->get_info()['redirect_url'])) {
        redirect($curl->get_info()['redirect_url']);
    } else {
        redirect($curl->get_info()['url']);
    }
} else {
    // Get token.
    $location = 'https://yoomoney.ru/oauth/token';

    $data = "code=$code&client_id=$config->client_id&grant_type=authorization_code" .
     "&redirect_uri=" . urlencode($CFG->wwwroot . "/payment/gateway/yoomoney/oauth2callback.php?id=$id&sesskey=" . sesskey()) .
     "&client_secret=$config->client_secret";

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
    $jsonresponse = $curl->post($location, $data, $options);

    $response = json_decode($jsonresponse);

    if (!empty($response->access_token)) {
        $config->token = $response->access_token;
        $gateway->set('config', json_encode($config));
        $gateway->update();
        redirect($CFG->wwwroot . "/payment/manage_gateway.php?id=" . $id);
    } else {
        throw new moodle_exception('tokenerror', 'payment');
    }
}
