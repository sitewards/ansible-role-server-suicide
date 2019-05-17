<?php
/**
 * @category    Sitewards
 * @package     sitewards-server-suicide
 * @copyright   Copyright (c) Sitewards GmbH (https://www.sitewards.com/)
 */

function main()
{
    $stdinBuffer = get_stdin();
    $hasStdin    = strlen($stdinBuffer) > 0;

    $options = getopt('', ['logfile:', 'termination:', 'interval:']);
    validate_options($options, !$hasStdin);

    $current_date_time = new DateTimeImmutable();

    if (!is_uptime_expired($options['interval'])) {
        return 0;
    }

    if ($hasStdin) {
        if (is_stdin_expired($stdinBuffer, $options['interval'])) {
            trigger_termination($options['termination']);
        
            return 1;
        }
    } else {
        if (is_access_log_expired($options['logfile'], $options['interval'])) {
            trigger_termination($options['termination']);

            return 1;
        }
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


function is_stdin_expired($stdin, $logExpiryInterval)
{
    // get datetime from the line and compare it to the current datetime
    preg_match('/\[(.*?)\]/', $stdin, $matches);

    // the access log is empty, or wrong format, consider it expired
    if (empty($matches)) {
        return 1;
    }

    $lastLogEntry    = new DateTimeImmutable($matches[1]);
    $expiryThreshold = new DateTimeImmutable('-' . $logExpiryInterval);

echo $lastLogEntry->format('Y-m-d H:i:s') . PHP_EOL;
echo $expiryThreshold->format('Y-m-d H:i:s') . PHP_EOL;

    // Check if latest access log entry is higher than the defined time interval
    return $lastLogEntry < $expiryThreshold;
}


function is_access_log_expired($logfile, $logExpiryInterval)
{
    // check last access time
    $logfileRef = escapeshellarg($logfile);
    $line       = `tail -n 1 $logfileRef`;

    // get datetime from the line and compare it to the current datetime
    preg_match('/\[(.*?)\]/', $line, $matches);

    // the access log is empty, or wrong format, let's try to check mtime
    if (empty($matches)) {
        $lastLogEntry = filemtime($logfile);
    } else {
        $lastLogEntry = new DateTimeImmutable($matches[1]);
    }

    $expiryThreshold = new DateTimeImmutable('-' . $logExpiryInterval);

    // Check if latest access log entry is higher than the defined time interval
    return $lastLogEntry < $expiryThreshold;
}

function is_uptime_expired($uptimeExpiryInterval)
{
    $upSince         = new DateTimeImmutable(shell_exec('uptime -s'));
    $expiryThreshold = new DateTimeImmutable('-' . $uptimeExpiryInterval);

    // Check if server is up for longer than the defined time interval
    return $upSince < $expiryThreshold;
}

function validate_options(&$options, $logfileRequired)
{
    if ($logfileRequired && !isset($options['logfile'])) {
        throw new RuntimeException('Access log file must be specified.');
    }

    if ($logfileRequired && !file_exists($options['logfile'])) {
        throw new RuntimeException('Access log file does not exist.');
    }

    if (!isset($options['termination'])) {
        throw new RuntimeException('Termination URL must be specified.');
    }

    $options['interval'] = trim($options['interval']);
    if (!isset($options['interval']) || !preg_match('/^\d+[a-z]+$/' , $options['interval'])) {
        $options['interval'] = '4days';
    }
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
