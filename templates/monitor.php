<?php
/**
 * @category    Sitewards
 * @package     sitewards-server-suicide
 * @copyright   Copyright (c) Sitewards GmbH (https://www.sitewards.com/)
 */

function main()
{
    $options = getopt('', ['logfile:', 'termination:', 'interval:']);
    validate_options($options);

    $current_date_time = new DateTimeImmutable();

    if (!is_uptime_expired($current_date_time, $options)) {
        return 0;
    }

    if (is_access_log_expired($current_date_time, $options)) {
        trigger_termination($options['termination']);
    }

    return 0;
}

function is_access_log_expired($current_date_time,$options)
{
    // check last access time
    $file_ref = escapeshellarg($options['logfile']);
    $line    = `tail -n 1 $file_ref`;

    // get datetime from the line and compare it to the current datetime
    preg_match('/\[(.*?)\]/', $line, $matches);

    // the access log is empty, no one touched the machine
    if (empty($matches)) {
        return true;
    }

    $last_date_time = new DateTimeImmutable($matches[1]);

    $interval = $last_date_time->diff($current_date_time);

    // Check if latest access log entry is higher than the defined time interval
    if ( (int) substr($options['interval'],0,-1) < (int) $interval->format('%' . substr($options['interval'], -1))) {
        return true;
    }

    return false;
}

function is_uptime_expired($current_date_time, $options)
{
    $uptime = new DateTimeImmutable(shell_exec('uptime -s'));
    $interval = $uptime->diff($current_date_time);

    // Check if server is up for longer than the defined time interval
    if ( (int) $options['interval'] < (int) $interval->format('%' . substr($options['interval'], -1))) {
        return true;
    }

    return false;
}

function validate_options(&$options)
{
    if (!isset($options['logfile'])) {
        throw new RuntimeException('Access log file must be specified.');
    }

    if (!file_exists($options['logfile'])) {
        throw new RuntimeException('Access log file does not exist.');
    }

    if (!isset($options['termination'])) {
        throw new RuntimeException('Termination URL must be specified.');
    }

    if (!isset($options['interval'])) {
        $options['interval'] = '4h';
    }
}

function trigger_termination($uri)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $uri);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);

    $response = curl_exec($ch);

    curl_close($ch);
}

main();

