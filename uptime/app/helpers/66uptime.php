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

defined('ALTUMCODE') || die();

function get_website_certificate($url, $port = 443) {
    try {
        $domain = parse_url($url, PHP_URL_HOST);

        $get = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => TRUE,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $read = @stream_socket_client('ssl://' . $domain . ':' . $port, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $get);

        if(!$read || $errstr) return null;

        $certificate_params = stream_context_get_params($read);

        $certificate = openssl_x509_parse($certificate_params['options']['ssl']['peer_certificate']);

        if(empty($certificate)) return null;

        $start_datetime = $certificate['validFrom_time_t'] ? (new \DateTime())->setTimestamp($certificate['validFrom_time_t']) : null;
        $end_datetime = $certificate['validTo_time_t'] ? (new \DateTime())->setTimestamp($certificate['validTo_time_t']) : null;
        $current_datetime = (new \DateTime());
        $is_valid = $start_datetime && $end_datetime && $current_datetime > $start_datetime && $current_datetime < $end_datetime;

        return empty($certificate) ? null : [
            'organization' => $certificate['issuer']['O'] ?? null,
            'common_name' => $certificate['issuer']['CN'] ?? null,
            'issuer_country' => $certificate['issuer']['C'] ?? null,
            'start_datetime' => $start_datetime ? $start_datetime->format('Y-m-d H:i:s') : null,
            'end_datetime' => $end_datetime ? $end_datetime->format('Y-m-d H:i:s') : null,
            'signature_type' => $certificate['signatureTypeSN'] ?? null,
            'is_valid' => $is_valid,
        ];

    } catch (\Exception $exception) {
        return null;
    }
}

