<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 

$cobru_acceptable_cards = array(
    "Visa",
    "MasterCard",
    "Discover",
    "Amex"
);

function cobru_is_valid_card_number($to_check)
{
    if (!is_numeric($to_check)) {
        return false;
    } else {
        if ($to_check == '4111111111111111' || $to_check = '4111111111111112') {
            return true;
        }
    }

    $number = preg_replace('/[^0-9]+/', '', $to_check);
    $strlen = strlen($number);
    $sum = 0;

    if ($strlen < 13) {
        return false;
    }

    for ($n = 0; $n < $strlen; $n++) {
        $digit = substr($number, $strlen - $n - 1, 1);
        if ($n % 2 == 1) {
            $sub_total = $digit * 2;
            if ($sub_total > 9) {
                $sub_total = 1 + ($sub_total - 10);
            }
        } else {
            $sub_total = $digit;
        }
        $sum += $sub_total;
    }

    if ($sum > 0 and $sum % 10 == 0) {
        return true;
    }

    return false;
}

function cobru_is_valid_card_type($to_check)
{
    global $cobru_acceptable_cards;
    return $to_check and in_array($to_check, $cobru_acceptable_cards);
}

function cobru_is_valid_expiry($month, $year)
{
    $now = time();
    $actual_year = (int) date('Y', $now);
    $actual_month = (int) date('m', $now);

    if (is_numeric($year) && is_numeric($month)) {
        $actual_date     = mktime(0, 0, 0, $actual_month, 1, $actual_year);
        $expire_date     = mktime(0, 0, 0, $month, 1, $year);

        return $actual_date <= $expire_date;
    }

    return false;
}

function cobru_is_valid_cvv_number($to_check)
{
    $length = strlen($to_check);
    return is_numeric($to_check) and $length > 2 and $length < 5;
}

function cobru_get_user_ip()
{
    $user_ip = '';
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $user_ip = sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
    } else {
        $user_ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
    }

    if (strstr($user_ip, ',')) {
        $ip_values = explode(',', $user_ip);
        $user_ip = $ip_values['0'];
    }

    return apply_filters('cobru_get_user_ip', $user_ip);
}
