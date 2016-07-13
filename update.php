<?php
/**
 * PHP Namecheap DynDNS update script
 * PHP Version 5
 * @author Robert Jarsen
 * @version 1.0
 * @copyright 2016 Robert Jarsen
 * @license MIT License
 *
 */

if (php_sapi_name() !== 'cli') {
  exit('Not in CLI mode');
}

require('./config.php');

/**
 * @param string $string
 */
function write_log($string) {
  $string = date('d-m-y H:i:s') . ' - ' . $string . "\n";
  file_put_contents('./log.txt', $string, FILE_APPEND | LOCK_EX);
}

/**
 * @param string $string
 * @return string bool $results
 */
function curl_get($string) {
  $ch = curl_init($string);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);

  $result = curl_exec($ch);

  if (!$result) {
    write_log(
      'ERROR: (cURL) ' .
      curl_error($ch)
    );
  }

  curl_close($ch);
  return $result;
}

/**
 * @return bool
 */
function update_check() {
  $result = curl_get('https://api.ipify.org');
  if (!$result) {
    write_log('ERROR: Issue connecting to ipify\'s API server');
    return false;
  }
  if (!filter_var($result, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    write_log('ERROR: A valid public IP address could not be retrieved');
    return true;
  }
  if (file_exists('./dyndns_ip_cache.txt')) {
    if (file_get_contents('./dyndns_ip_cache.txt') === $result) {
      return false;
    }
  }
  write_log('IP CHANGE: Your new public IP address -- ' . $result);
  file_put_contents('./dyndns_ip_cache.txt', $result, LOCK_EX);
  return true;
}

/**
 * @param array $array
 * @return bool
 */
function update($array) {
  if (!update_check()) {
    return false;
  }
  foreach ($array as &$record) {
    array_push($record, 'ip');
    $record['ip'] = file_get_contents('./dyndns_ip_cache.txt');
    $result = curl_get('https://dynamicdns.park-your-domain.com/update?' . http_build_query($record));
    if (!$result) {
      write_log('ERROR: Issue connecting to Namecheap\'s dyndns update server');
      return false;
    }

    $xml_data = "$result";
    $parsed = simplexml_load_string($xml_data);

    if (isset($parsed->ErrCount) && $parsed->ErrCount > 0) {
      $errors = (array) $parsed->errors;
      foreach ($errors as $key => $value) {
        write_log(
          ''
          . 'ERROR: '
          . $value
          . ' (d: '
          . $record['domain']
          . ') (h: '
          . $record['host']
          . ')'
        );
      }
    } else {
      write_log(
        ''
        . 'UPDATED: '
        . $record['ip']
        . ' (d: '
        . $record['domain']
        . ') (h: '
        . $record['host']
        . ')'
      );
    }
  }
  if (isset($errors)) {
    return false;
  }
  return true;
}



update($dyndns_records);

exit();
