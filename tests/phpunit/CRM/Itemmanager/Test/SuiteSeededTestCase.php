<?php

/**
 * Suite-level seeded base class (seed once per test run).
 */
abstract class CRM_Itemmanager_Test_SuiteSeededTestCase extends CRM_Itemmanager_Test_SeededTestCase {

  protected static bool $suiteSeeded = FALSE;
  protected static bool $shutdownRegistered = FALSE;

  public function setUp(): void {
    if (!self::$suiteSeeded) {
      parent::setUp();
      self::$suiteSeeded = TRUE;

      if (!self::$shutdownRegistered) {
        self::$shutdownRegistered = TRUE;
        register_shutdown_function(function() {
          $this->cleanupSeeds();
        });
      }
    }
  }

  public function tearDown(): void {
    // No per-test cleanup; handled once at shutdown.
  }

}
