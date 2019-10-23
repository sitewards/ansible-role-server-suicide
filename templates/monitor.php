<?php
/**
 * @category    Sitewards
 * @package     sitewards-server-suicide
 * @copyright   Copyright (c) Sitewards GmbH (https://www.sitewards.com/)
 */

function main()
{
    $options = getopt('', ['logfile:', 'termination:', 'interval:', 'syslog:']);
    validate_options($options);
    $expiryThreshold = new DateTimeImmutable('-' . $options['interval']);

    if (!is_uptime_expired($options['interval'])) {
        return 0;
    }

    $terminate = false;
    if (!empty($options['logfile'])) {
        $terminate = is_access_log_expired($options['logfile'], $expiryThreshold);
    } elseif (!empty($options['syslog'])){
        $terminate = is_sys_log_expired($options['syslog'], $expiryThreshold);
    } else {
        $stdinBuffer = get_stdin();
        // check if standard input has content
        if(strlen($stdinBuffer) > 0){
            $terminate = is_stdin_expired($stdinBuffer, $expiryThreshold);
        } else {
            throw new RuntimeException('No input from standard in. Access log file or system log must be specified.');
        }
    }

    if($terminate){
        trigger_termination($options['termination']);
        return 1;
    }

    return 0;
}

function get_stdin()
{
    $stdinHandle = fopen('php://stdin', 'r');

    if(!$stdinHandle) {
        return '';
    }

    // prevent blocking on empty input
    stream_set_blocking($stdinHandle, false);

    $stdinBuffer = ''; 
    $stdinBufferPrev = '';
    while (!feof($stdinHandle)) {
      $stdinBufferPrev = $stdinBuffer;
      // read *one* line from stdin upto "\r\n"
      $stdinBuffer = trim(fgets($stdinHandle));
    } 

    fclose($stdinHandle);
    return $stdinBufferPrev;
}


function is_stdin_expired($stdin, DateTimeImmutable $expiryThreshold)
{
    $lastLogEntry = get_date_from_text($stdin);

    // the access log is empty, or wrong format, consider it expired
    if (!$lastLogEntry) {
        return true;
    }

    // Check if latest access log entry is higher than the defined time interval
    return $lastLogEntry < $expiryThreshold;
}


function is_access_log_expired($logfile, DateTimeImmutable $expiryThreshold)
{
    // check last access time
    $logfileRef = escapeshellarg($logfile);
    $line       = `tail -n 1 $logfileRef`;

    // get datetime from the line and compare it to the current datetime
    $lastLogEntry = get_date_from_text($line);

    // the access log is empty, or wrong format, let's try to check mtime
    if (!$lastLogEntry) {
        $lastLogEntry = DateTimeImmutable::createFromFormat('U', filemtime($logfile));
    }

    // Check if latest access log entry is higher than the defined time interval
    return $lastLogEntry < $expiryThreshold;
}

function get_date_from_text($text){
    // to identify date in log regexp searches for a first value between square brackets that have at least 10 chars
    preg_match('/\[([^\]]{10,}?)\]/', $text, $matches);
    if(empty($matches)){
        return false;
    } else {
        return new DateTimeImmutable($matches[1]);
    }
}

/**
 * Checks for the last log entry in the specified unit and compare it to the expiry threshold.
 *
 * @param                   $unit
 * @param DateTimeImmutable $expiryThreshold
 *
 * @return bool
 */
function is_sys_log_expired($unit, DateTimeImmutable $expiryThreshold): bool
{
    $unitRef      = escapeshellarg($unit);
    $lastLogEvent = json_decode(`journalctl -u $unitRef -n 1 -o json`, true);
    if (empty($lastLogEvent)) {
        // the access log is empty, or wrong format, consider it expired
        return false;
    }
    // convert milliseconds into seconds from begin of unix epoch
    $lastLogTimeSeconds = substr($lastLogEvent['__REALTIME_TIMESTAMP'], 0, -6);
    $lastLogEntry       = DateTimeImmutable::createFromFormat('U', $lastLogTimeSeconds);
    return $lastLogEntry < $expiryThreshold;
}

function is_uptime_expired($uptimeExpiryInterval)
{
    $upSince         = new DateTimeImmutable(shell_exec('uptime -s'));
    $expiryThreshold = new DateTimeImmutable('-' . $uptimeExpiryInterval);

    // Check if server is up for longer than the defined time interval
    return $upSince < $expiryThreshold;
}

function validate_options(&$options)
{

    if (!empty($options['logfile']) && !file_exists($options['logfile'])) {
        throw new RuntimeException('Access log file does not exist.');
    }

    if (!isset($options['termination'])) {
        throw new RuntimeException('Termination URL must be specified.');
    }

    if (!isset($options['interval']) || !preg_match('/^\d+[a-z]+$/' , $options['interval'])) {
        $options['interval'] = '4days';
    }
    $options['interval'] = trim($options['interval']);
}

function trigger_termination($urlOrShellCommand)
{
    if (filter_var($urlOrShellCommand, FILTER_VALIDATE_URL) !== false) {
        trigger_termination_via_url($urlOrShellCommand);
    } else {
        trigger_termination_via_cli($urlOrShellCommand);
    }
}

function trigger_termination_via_cli($shellCommand)
{
    system($shellCommand);
}

function trigger_termination_via_url($uri)
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

exit(main());
