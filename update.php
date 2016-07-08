<?php
  if (php_sapi_name() !== 'cli') {
    exit('Not in CLI mode');
  }

  require('./config.php');

  function dd_log_this($string, $bool) {
    if (!$bool) { $string = 'ERROR: ' . $string; } else { $string = 'UPDATED: ' . $string; }
    $string = date('d-m-y H:i:s') . ' - ' . $string . "\n";
    file_put_contents('./log.txt', $string, FILE_APPEND | LOCK_EX);
    echo $string;
  }

  function dd_simple_curl($string) {
    $ch = curl_init($string);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
    $result = curl_exec($ch);
    if (!$result) {
      dd_log_this(curl_error($ch), 0);
    }
    curl_close($ch);
    return $result;
  }

  function dd_has_ip_changed() {
    $result = dd_simple_curl('https://api.ipify.org');
    if (!$result) { dd_log_this('Issue connecting to ipify\'s API server', 0); return 0; }
    if (filter_var($result, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
      if (file_exists('./dyndns_ip_cache.txt')) {
        if (file_get_contents('./dyndns_ip_cache.txt') === $result) {
          return 0;
        }
      }
      file_put_contents('./dyndns_ip_cache.txt', $result, LOCK_EX);
      return 1;
    }
    dd_log_this('A valid public IP address could not be retrieved', 0);
  }

  function dd_update_records($array) {
    foreach ($array as &$record) {
      array_push($record, 'ip');
      $record['ip'] = file_get_contents('./dyndns_ip_cache.txt');
      $result = dd_simple_curl('https://dynamicdns.park-your-domain.com/update?' . http_build_query($record));
      if (!$result) { dd_log_this('Issue connecting to Namecheap\'s dyndns update server', 0); return 0; }
      $xml_data = "$result";
      $parsed = simplexml_load_string($xml_data);
      if (isset($parsed->ErrCount) && $parsed->ErrCount > 0) {
        $errors = (array) $parsed->errors;
        foreach ($errors as $key => $value) {
          dd_log_this($value . ' (d: ' . $record['domain'] . ') (h: ' . $record['host'] . ')', 0);
        }
      } else {
        dd_log_this($record['ip'] . ' (d: ' . $record['domain'] . ') (h: ' . $record['host'] . ')', 1);
      }
    }
    if (isset($errors)) { return 0; } return 1;
  }

  function dyndns_update($array) { if (dd_has_ip_changed() && dd_update_records($array)) { exit(0); } exit(1); }

  dyndns_update($dyndns_records);
