<?php

ini_set('memory_limit', '2G');
ini_set('safe_mode', 0);

// Ensure CiviCRM bootstrap env is available for cv during tests.
if (!getenv('CIVICRM_SETTINGS')) {
  putenv('CIVICRM_SETTINGS=/root/.openclaw/workspace/civicrm-buildkit/build/stable/web/private/civicrm.settings.php');
}
if (!getenv('CIVICRM_BOOT')) {
  putenv('CIVICRM_BOOT=Standalone://root/.openclaw/workspace/civicrm-buildkit/build/stable');
}
if (!getenv('CIVICRM_DSN')) {
  $dsn = 'mysql://stablecivi_dbz7q:M5dlmBEOR9YAsD4w@127.0.0.1/stablecivi_6m8zl?new_link=true';
  putenv('CIVICRM_DSN=' . $dsn);
  $_ENV['CIVICRM_DSN'] = $dsn;
  $_SERVER['CIVICRM_DSN'] = $dsn;
}
if (!defined('CIVICRM_DSN')) {
  define('CIVICRM_DSN', getenv('CIVICRM_DSN'));
}
$GLOBALS['CIVICRM_DSN'] = getenv('CIVICRM_DSN');

// phpcs:disable
eval(cv('php:boot --level=classloader', 'phpcode'));
// phpcs:enable
// Allow autoloading of PHPUnit helper classes in this extension.
$loader = new \Composer\Autoload\ClassLoader();
$loader->add('CRM_', __DIR__);
$loader->add('Civi\\', __DIR__);
$loader->add('api_', __DIR__);
$loader->add('api\\', __DIR__);
// Helper classes
$loader->add('Helper_', __DIR__ . '/helper');
$loader->add('Helper\\', __DIR__ . '/helper');
$loader->register();

/**
 * Call the "cv" command.
 *
 * @param string $cmd
 *   The rest of the command to send.
 * @param string $decode
 *   Ex: 'json' or 'phpcode'.
 * @return string
 *   Response output (if the command executed normally).
 * @throws \RuntimeException
 *   If the command terminates abnormally.
 */
function cv($cmd, $decode = 'json') {
  $cmd = 'cv ' . $cmd;
  $descriptorSpec = array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => STDERR);
  $oldOutput = getenv('CV_OUTPUT');
  putenv("CV_OUTPUT=json");

  // Execute `cv` in the original folder. This is a work-around for
  // phpunit/codeception, which seem to manipulate PWD.
  $cmd = sprintf('cd %s; %s', escapeshellarg(getenv('PWD')), $cmd);

  $process = proc_open($cmd, $descriptorSpec, $pipes, __DIR__);
  putenv("CV_OUTPUT=$oldOutput");
  fclose($pipes[0]);
  $result = stream_get_contents($pipes[1]);
  fclose($pipes[1]);
  if (proc_close($process) !== 0) {
    throw new RuntimeException("Command failed ($cmd):\n$result");
  }
  switch ($decode) {
    case 'raw':
      return $result;

    case 'phpcode':
      // If the last output is /*PHPCODE*/, then we managed to complete execution.
      if (substr(trim($result), 0, 12) !== "/*BEGINPHP*/" || substr(trim($result), -10) !== "/*ENDPHP*/") {
        throw new \RuntimeException("Command failed ($cmd):\n$result");
      }
      return $result;

    case 'json':
      return json_decode($result, 1);

    default:
      throw new RuntimeException("Bad decoder format ($decode)");
  }
}
