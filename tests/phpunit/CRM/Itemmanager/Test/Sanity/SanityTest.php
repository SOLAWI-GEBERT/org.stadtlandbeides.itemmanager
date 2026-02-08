<?php

use CRM_Itemmanager_ExtensionUtil as E;
use Civi\Test\HookInterface;

/**
 * Basic sanity checks for the extension.
 *
 * @group headless
 */
class CRM_Itemmanager_Test_SanityTest extends CRM_Itemmanager_Test_SeededTestCase implements HookInterface {

  /**
   * Extension should be discoverable and (if installed) active.
   */
  public function testExtensionInstalled(): void {
    $keys = \CRM_Extension_System::singleton(TRUE)->getFullContainer()->getKeys();
    $this->assertContains(E::LONG_NAME, $keys, 'Extension key not found in container');

    // Optional: if a row exists in civicrm_extension, it should at least be readable.
    $ext = civicrm_api3('Extension', 'get', [
      'full_name' => E::LONG_NAME,
    ]);
    $this->assertNotNull($ext);
  }

}
