<?php

/**
 * Suite-level seeds with a test user + membership.
 */
abstract class CRM_Itemmanager_Test_MembershipSeededTestCase extends CRM_Itemmanager_Test_SuiteSeededTestCase {

  protected static bool $membershipSeeded = FALSE;
  protected static array $membershipSeedIds = [];

  public function setUp(): void {
    parent::setUp();

    $needsSeed = !self::$membershipSeeded;

    if (!$needsSeed) {
      $contactId = self::$membershipSeedIds['member_contact'][0] ?? 0;
      if ($contactId) {
        $exists = \Civi\Api4\Contact::get(FALSE)
          ->addWhere('id', '=', $contactId)
          ->execute()->first();
        if (empty($exists['id'])) {
          $needsSeed = TRUE;
        }
      }
      else {
        $needsSeed = TRUE;
      }
    }

    if ($needsSeed) {
      $this->seedMembership();
      self::$membershipSeeded = TRUE;
      // Only store membership-specific keys.
      self::$membershipSeedIds = array_intersect_key($this->seedIds,
        array_flip(['member_contact', 'order']));
    }
    else {
      // Add membership keys without overwriting fresh base IDs.
      $this->seedIds = $this->seedIds + self::$membershipSeedIds;
    }
  }

  protected function seedMembership(): void {
    // Create test user (contact).
    $contact = \Civi\Api4\Contact::create(FALSE)
      ->addValue('contact_type', 'Individual')
      ->addValue('first_name', 'Unit')
      ->addValue('last_name', 'Member')
      ->execute()
      ->first();

    if (!empty($contact['id'])) {
      $this->seedIds['member_contact'][] = $contact['id'];
    }

    // Create membership via PriceSet/PriceFieldValue (Order API3).
    $priceFieldId = $this->seedIds['price_field'][0] ?? NULL;
    $priceFieldValueId = $this->seedIds['price_field_value'][0] ?? NULL;
    $membershipTypeId = $this->seedIds['membership_type'][0] ?? NULL;
    $financialTypeId = $this->seedIds['financial_type'][0] ?? NULL;

    if (!$priceFieldId || !$priceFieldValueId || !$membershipTypeId || !$financialTypeId) {
      throw new \RuntimeException('Membership seed missing IDs: price_field_id/price_field_value_id/membership_type_id/financial_type_id');
    }

    $order = civicrm_api3('Order', 'create', [
      'contact_id' => $contact['id'] ?? NULL,
      'total_amount' => 100,
      'financial_type_id' => $financialTypeId,
      'is_test' => 1,
      'line_items' => [
        [
          'params' => [
            'contact_id' => $contact['id'] ?? NULL,
            'membership_type_id' => $membershipTypeId,
          ],
          'line_item' => [
            [
              'price_field_id' => $priceFieldId,
              'price_field_value_id' => $priceFieldValueId,
              'qty' => 1,
              'unit_price' => 100,
              'line_total' => 100,
              'financial_type_id' => $financialTypeId,
              'membership_type_id' => $membershipTypeId,
            ],
          ],
        ],
      ],
    ]);

    if (!empty($order['id'])) {
      $this->seedIds['order'][] = $order['id'];
    }
    else {
      throw new \RuntimeException('Order.create failed: ' . print_r($order, TRUE));
    }
  }

  public function tearDown(): void {
    // No per-test cleanup for shared membership seed data.
    // Cleanup happens via TransactionalInterface rollback or at script shutdown.
    parent::tearDown();
  }

  public static function tearDownAfterClass(): void {
    self::$membershipSeeded = FALSE;
    self::$membershipSeedIds = [];
    parent::tearDownAfterClass();
  }

}
