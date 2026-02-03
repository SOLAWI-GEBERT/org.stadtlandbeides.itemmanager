<?php

/**
 * Suite-level seeded base class (seed once per test run).
 */
abstract class CRM_Itemmanager_Test_SuiteSeededTestCase extends CRM_Itemmanager_Test_SeededTestCase {

  protected static bool $suiteSeeded = FALSE;
  protected static bool $shutdownRegistered = FALSE;
  protected static array $suiteSeedIds = [];
  protected static ?self $seedInstance = NULL;

  public function setUp(): void {
    if (!self::$suiteSeeded) {
      parent::setUp();
      self::$suiteSeeded = TRUE;
      self::$suiteSeedIds = $this->seedIds;
      self::$seedInstance = $this;

      if (!self::$shutdownRegistered) {
        self::$shutdownRegistered = TRUE;
        register_shutdown_function(function() {
          if (self::$seedInstance) {
            self::$seedInstance->cleanupSuiteSeeds();
          }
        });
      }
    }
    else {
      // Reuse suite-level seed ids for subsequent tests.
      $this->seedIds = self::$suiteSeedIds;
    }
  }

  public function tearDown(): void {
    // No per-test cleanup; handled once at shutdown.
  }

  public function cleanupSuiteSeeds(): void {
    $this->cleanupSeeds();
  }

}
