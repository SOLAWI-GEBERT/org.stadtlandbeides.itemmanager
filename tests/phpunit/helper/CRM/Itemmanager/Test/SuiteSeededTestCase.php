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

      // If headless reset wiped the DB, rebuild seeds.
      $priceSetId = $this->seedIds['price_set'][0] ?? NULL;
      $priceFieldId = $this->seedIds['price_field'][0] ?? NULL;
      $priceFieldValueId = $this->seedIds['price_field_value'][0] ?? NULL;
      $membershipTypeId = $this->seedIds['membership_type'][0] ?? NULL;

      $missing = FALSE;
      if ($priceSetId) {
        $exists = \Civi\Api4\PriceSet::get(FALSE)
          ->addWhere('id', '=', $priceSetId)
          ->execute()
          ->first();
        if (empty($exists['id'])) {
          $missing = TRUE;
        }
      }
      else {
        $missing = TRUE;
      }

      if (!$missing && $priceFieldId) {
        $exists = \Civi\Api4\PriceField::get(FALSE)
          ->addWhere('id', '=', $priceFieldId)
          ->execute()
          ->first();
        if (empty($exists['id'])) {
          $missing = TRUE;
        }
      }
      elseif (!$priceFieldId) {
        $missing = TRUE;
      }

      if (!$missing && $priceFieldValueId) {
        $exists = \Civi\Api4\PriceFieldValue::get(FALSE)
          ->addWhere('id', '=', $priceFieldValueId)
          ->execute()
          ->first();
        if (empty($exists['id'])) {
          $missing = TRUE;
        }
      }
      elseif (!$priceFieldValueId) {
        $missing = TRUE;
      }

      if (!$missing && $membershipTypeId) {
        $exists = \Civi\Api4\MembershipType::get(FALSE)
          ->addWhere('id', '=', $membershipTypeId)
          ->execute()
          ->first();
        if (empty($exists['id'])) {
          $missing = TRUE;
        }
      }
      elseif (!$membershipTypeId) {
        $missing = TRUE;
      }

      if ($missing) {
        parent::setUp();
        self::$suiteSeedIds = $this->seedIds;
        self::$seedInstance = $this;
      }
    }
  }

  public function tearDown(): void {
    // No per-test cleanup; handled once at shutdown.
  }

  public static function tearDownAfterClass(): void {
    // Reset suite-level flags so the next test class gets a fresh seed.
    self::$suiteSeeded = FALSE;
    self::$suiteSeedIds = [];
    parent::tearDownAfterClass();
  }

  public function cleanupSuiteSeeds(): void {
    $this->cleanupSeeds();
  }

}
