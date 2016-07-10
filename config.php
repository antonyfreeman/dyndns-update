<?php
debug_backtrace() || exit('Direct access not permitted');

date_default_timezone_set('America/New_York');

$dyndns_records = array(
  array(
    'host' => '@',
    'domain' => 'domain.tld',
    'password' => '00000000000000000000000000000000'
  ),
  array(
    'host' => 'www',
    'domain' => 'domain.tld',
    'password' => '00000000000000000000000000000000'
  ),
  // ... more
);
