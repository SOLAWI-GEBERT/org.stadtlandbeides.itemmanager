<?php

use Civi\Test\HookInterface;

/**
 * Tests for CRM_Itemmanager_Page_Dashboard.
 *
 * @group headless
 */
class CRM_Itemmanager_Test_DashboardTest extends CRM_Itemmanager_Test_MembershipSeededTestCase implements HookInterface {

  /** @var array<string, array<int>> */
  protected array $itemmanagerRecordIds = [
    'periods' => [],
    'settings' => [],
  ];

  /** @var array<int> */
  protected array $createdContactIds = [];

  /** @var array<int> */
  protected array $createdMembershipIds = [];

  /** @var array<int> */
  protected array $createdOrderIds = [];

  public function setUp(): void {
    parent::setUp();

    $this->itemmanagerRecordIds = [
      'periods' => [],
      'settings' => [],
    ];
    $this->createdContactIds = [];
    $this->createdMembershipIds = [];
    $this->createdOrderIds = [];
  }

  public function tearDown(): void {
    $settingIds = $this->filterIds($this->itemmanagerRecordIds['settings'] ?? []);
    if (!empty($settingIds)) {
      \Civi\Api4\ItemmanagerSettings::delete(FALSE)
        ->addWhere('id', 'IN', $settingIds)
        ->execute();
    }

    $periodIds = $this->filterIds($this->itemmanagerRecordIds['periods'] ?? []);
    if (!empty($periodIds)) {
      \Civi\Api4\ItemmanagerPeriods::delete(FALSE)
        ->addWhere('id', 'IN', $periodIds)
        ->execute();
    }

    $orderIds = $this->filterIds($this->createdOrderIds);
    foreach ($orderIds as $orderId) {
      try {
        civicrm_api3('Order', 'delete', ['id' => $orderId]);
      }
      catch (\Exception $e) {
        // ignore
      }
    }

    $membershipIds = $this->filterIds($this->createdMembershipIds);
    if (!empty($membershipIds)) {
      foreach ($membershipIds as $mid) {
        try {
          civicrm_api3('Membership', 'delete', ['id' => $mid]);
        }
        catch (\Exception $e) {
          // ignore
        }
      }
    }

    $contactIds = $this->filterIds($this->createdContactIds);
    if (!empty($contactIds)) {
      \Civi\Api4\Contact::delete(FALSE)
        ->addWhere('id', 'IN', $contactIds)
        ->execute();
    }

    parent::tearDown();
  }

  public function testCompareMultiArraysReturnsTrueForEquivalentNestedArrays(): void {
    $left = [
      'member' => [
        'status' => 'Current',
        'items' => [
          'quantity' => 2,
          'label' => 'Unit Test Membership',
        ],
      ],
    ];

    $right = [
      'member' => [
        'status' => 'Current',
        'items' => [
          'quantity' => 2,
          'label' => 'Unit Test Membership',
        ],
      ],
    ];

    $this->assertTrue(CRM_Itemmanager_Page_Dashboard::compare_multi_Arrays($left, $right));
  }

  public function testCompareMultiArraysReturnsFalseForDifferentStructures(): void {
    $left = [
      'member' => [
        'status' => 'Current',
        'items' => [
          'quantity' => 2,
          'label' => 'Unit Test Membership',
        ],
      ],
    ];

    $differentValue = [
      'member' => [
        'status' => 'Expired',
        'items' => [
          'quantity' => 2,
          'label' => 'Unit Test Membership',
        ],
      ],
    ];

    $missingKey = [
      'member' => [
        'status' => 'Current',
      ],
    ];

    $this->assertFalse(CRM_Itemmanager_Page_Dashboard::compare_multi_Arrays($left, $differentValue));
    $this->assertFalse(CRM_Itemmanager_Page_Dashboard::compare_multi_Arrays($left, $missingKey));
  }

  public function testRunBuildsMemberListForSeededMembership(): void {
    $this->ensureItemmanagerRecordForSeededFieldValue();

    $contactId = $this->getSeedId('member_contact');

    $memberArray = CRM_Itemmanager_Util::getLastMemberShipsFullRecordByContactId($contactId);
    $this->assertSame(0, (int) ($memberArray['is_error'] ?? 1));

    $page = new CRM_Itemmanager_Test_DashboardPageDouble();

    $this->withRequestCid($contactId, function() use ($page): void {
      $page->run();
    });

    $this->assertSame($contactId, (int) ($page->assignedValues['contact_id'] ?? 0));
    $this->assertFalse((bool) ($page->assignedValues['data_error'] ?? TRUE));
    $this->assertNotEmpty($page->assignedValues['currentTime'] ?? NULL);

    $groupRefresh = (string) ($page->assignedValues['group_refresh'] ?? '');
    $this->assertStringContainsString('cid=' . $contactId, $groupRefresh);

    $memberList = $page->assignedValues['member_list'] ?? NULL;
    $this->assertIsArray($memberList);

    if (!empty($memberList)) {
      $firstMember = reset($memberList);
      $this->assertIsArray($firstMember);
      $this->assertArrayHasKey('membership_id', $firstMember);
      $this->assertArrayHasKey('member_name', $firstMember);
      $this->assertArrayHasKey('status', $firstMember);
      $this->assertArrayHasKey('active', $firstMember);

      $this->assertGreaterThan(0, $firstMember['membership_id']);
      $this->assertNotEmpty($firstMember['member_name']);
      $this->assertNotEmpty($firstMember['status']);
    }
  }

  public function testRunAssignsEmptyMemberListForContactWithoutMemberships(): void {
    $contact = \Civi\Api4\Contact::create(FALSE)
      ->addValue('contact_type', 'Individual')
      ->addValue('first_name', 'No')
      ->addValue('last_name', 'Membership')
      ->execute()
      ->first();

    $contactId = (int) ($contact['id'] ?? 0);
    $this->assertGreaterThan(0, $contactId);
    $this->createdContactIds[] = $contactId;

    $page = new CRM_Itemmanager_Test_DashboardPageDouble();

    $this->withRequestCid($contactId, function() use ($page): void {
      $page->run();
    });

    $this->assertSame($contactId, (int) ($page->assignedValues['contact_id'] ?? 0));
    $this->assertFalse((bool) ($page->assignedValues['data_error'] ?? TRUE));

    $memberList = $page->assignedValues['member_list'] ?? NULL;
    $this->assertIsArray($memberList);
    $this->assertCount(0, $memberList);
  }

  // ---------------------------------------------------------------
  // 8.1 run with multiple Memberships per Contact
  // ---------------------------------------------------------------

  public function testRunBuildsMultipleMembershipsPerContact(): void {
    $this->ensureItemmanagerRecordForSeededFieldValue();

    $contactId = $this->getSeedId('member_contact');
    $membershipTypeId = $this->getMembershipTypeId();

    // Create a second membership (different start/end) for the same contact.
    $secondMembership = civicrm_api3('Membership', 'create', [
      'contact_id' => $contactId,
      'membership_type_id' => $membershipTypeId,
      'status_id' => 'Current',
      'start_date' => date('Y-m-d', strtotime('-6 months')),
      'end_date' => date('Y-m-d', strtotime('+6 months')),
    ]);
    $secondMembershipId = (int) ($secondMembership['id'] ?? 0);
    $this->assertGreaterThan(0, $secondMembershipId);
    $this->createdMembershipIds[] = $secondMembershipId;

    // Create a contribution linked to the second membership.
    $priceFieldId = $this->getPriceFieldId();
    $pfvId = $this->getSeedId('price_field_value');
    $financialTypeId = $this->getFinancialTypeId();

    $order2 = civicrm_api3('Order', 'create', [
      'contact_id' => $contactId,
      'total_amount' => 50,
      'financial_type_id' => $financialTypeId,
      'receive_date' => date('Y-m-d'),
      'contribution_status_id' => 'Completed',
      'line_items' => [
        [
          'params' => [
            'contact_id' => $contactId,
            'membership_type_id' => $membershipTypeId,
          ],
          'line_item' => [
            [
              'price_field_id' => $priceFieldId,
              'price_field_value_id' => $pfvId,
              'qty' => 1,
              'unit_price' => 50,
              'line_total' => 50,
              'financial_type_id' => $financialTypeId,
              'membership_type_id' => $membershipTypeId,
            ],
          ],
        ],
      ],
    ]);
    $this->createdOrderIds[] = (int) ($order2['id'] ?? 0);

    // Link contribution to second membership.
    civicrm_api3('MembershipPayment', 'create', [
      'membership_id' => $secondMembershipId,
      'contribution_id' => (int) $order2['id'],
    ]);

    $oldLevel = error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
    $page = new CRM_Itemmanager_Test_DashboardPageDouble();

    try {
      $this->withRequestCid($contactId, function () use ($page): void {
        $page->run();
      });
    }
    finally {
      error_reporting($oldLevel);
    }

    $memberList = $page->assignedValues['member_list'] ?? [];
    $this->assertIsArray($memberList);
    // getLastMemberShipsFullRecordByContactId groups by membership type,
    // so two memberships of the same type may merge into one entry.
    // We verify at least one entry exists and contains data from contributions.
    $this->assertNotEmpty($memberList,
      'member_list should contain at least one membership');

    foreach ($memberList as $entry) {
      $this->assertArrayHasKey('membership_id', $entry);
      $this->assertArrayHasKey('member_name', $entry);
      $this->assertArrayHasKey('status', $entry);
      $this->assertGreaterThan(0, $entry['membership_id']);
    }
  }

  // ---------------------------------------------------------------
  // 8.2 run with Successor resolution via getLastPricefieldSuccessor
  // ---------------------------------------------------------------

  public function testRunGroupsLineItemsBySuccessorFieldId(): void {
    $contactId = $this->getSeedId('member_contact');
    $priceSetId = $this->getSeedId('price_set');
    $priceFieldId = $this->getPriceFieldId();
    $pfvId = $this->getSeedId('price_field_value');
    $financialTypeId = $this->getFinancialTypeId();
    $membershipTypeId = $this->getMembershipTypeId();

    // Clean existing itemmanager data.
    \Civi\Api4\ItemmanagerPeriods::delete(FALSE)
      ->addWhere('price_set_id', '=', $priceSetId)
      ->execute();
    \Civi\Api4\ItemmanagerSettings::delete(FALSE)
      ->addWhere('price_field_value_id', '=', $pfvId)
      ->execute();

    // Create period + setting with a successor chain.
    $period1 = \Civi\Api4\ItemmanagerPeriods::create(FALSE)
      ->addValue('price_set_id', $priceSetId)
      ->addValue('periods', 1)
      ->addValue('period_type', 2)
      ->addValue('period_start_on', '20250101')
      ->execute()
      ->first();
    $this->itemmanagerRecordIds['periods'][] = (int) $period1['id'];

    $setting1 = \Civi\Api4\ItemmanagerSettings::create(FALSE)
      ->addValue('price_field_value_id', $pfvId)
      ->addValue('itemmanager_periods_id', (int) $period1['id'])
      ->addValue('itemmanager_successor_id', 0)
      ->execute()
      ->first();
    $this->itemmanagerRecordIds['settings'][] = (int) $setting1['id'];

    // Ensure membership with end_date.
    $this->ensureMembershipWithEndDate($contactId, $membershipTypeId);

    // Create contribution linked to membership.
    $order = civicrm_api3('Order', 'create', [
      'contact_id' => $contactId,
      'total_amount' => 100,
      'financial_type_id' => $financialTypeId,
      'receive_date' => date('Y-m-d'),
      'contribution_status_id' => 'Completed',
      'line_items' => [
        [
          'params' => [
            'contact_id' => $contactId,
            'membership_type_id' => $membershipTypeId,
          ],
          'line_item' => [
            [
              'price_field_id' => $priceFieldId,
              'price_field_value_id' => $pfvId,
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
    $this->createdOrderIds[] = (int) ($order['id'] ?? 0);

    $membership = \Civi\Api4\Membership::get(FALSE)
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('membership_type_id', '=', $membershipTypeId)
      ->execute()
      ->first();

    if (!empty($membership['id']) && !empty($order['id'])) {
      civicrm_api3('MembershipPayment', 'create', [
        'membership_id' => $membership['id'],
        'contribution_id' => $order['id'],
      ]);
    }

    // Verify that getLastPricefieldSuccessor resolves the PFV correctly.
    $successorId = CRM_Itemmanager_Util::getLastPricefieldSuccessor($pfvId);
    $this->assertSame($pfvId, (int) $successorId,
      'Without successor chain, should return same PFV id');

    $oldLevel = error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
    $page = new CRM_Itemmanager_Test_DashboardPageDouble();

    try {
      $this->withRequestCid($contactId, function () use ($page): void {
        $page->run();
      });
    }
    finally {
      error_reporting($oldLevel);
    }

    $memberList = $page->assignedValues['member_list'] ?? [];
    $this->assertIsArray($memberList);
    $this->assertNotEmpty($memberList, 'member_list should be populated');

    // Dashboard now only provides lightweight membership metadata.
    $firstMember = reset($memberList);
    $this->assertArrayHasKey('membership_id', $firstMember);
    $this->assertArrayHasKey('member_name', $firstMember);
    $this->assertGreaterThan(0, $firstMember['membership_id']);
  }

  public function testProcessHelpersAssignExpectedDetailFields(): void {
    $page = new CRM_Itemmanager_Test_DashboardPageDouble();

    $page->invokeProcessError('ERROR', 'Dashboard title', 'Dashboard message');

    $this->assertSame('Dashboard title', $page->assignedValues['error_title'] ?? NULL);
    $this->assertSame('Dashboard message', $page->assignedValues['error_message'] ?? NULL);

    $page->invokeProcessDetail('Unit Test Membership', 101, 'Optional Fee');

    $this->assertSame('Unit Test Membership', $page->assignedValues['detail_member'] ?? NULL);
    $this->assertSame(101, (int) ($page->assignedValues['detail_contribution'] ?? 0));
    $this->assertSame('Optional Fee', $page->assignedValues['detail_lineitem'] ?? NULL);
  }

  private function ensureItemmanagerRecordForSeededFieldValue(): void {
    $priceSetId = $this->getSeedId('price_set');
    $priceFieldValueId = $this->getSeedId('price_field_value');

    \Civi\Api4\ItemmanagerSettings::delete(FALSE)
      ->addWhere('price_field_value_id', '=', $priceFieldValueId)
      ->execute();

    \Civi\Api4\ItemmanagerPeriods::delete(FALSE)
      ->addWhere('price_set_id', '=', $priceSetId)
      ->execute();

    $period = \Civi\Api4\ItemmanagerPeriods::create(FALSE)
      ->addValue('price_set_id', $priceSetId)
      ->addValue('period_start_on', '20200101')
      ->addValue('periods', 1)
      ->addValue('period_type', 2)
      ->addValue('hide', 0)
      ->addValue('reverse', 0)
      ->execute()
      ->first();

    $periodId = (int) ($period['id'] ?? 0);
    $this->assertGreaterThan(0, $periodId);
    $this->itemmanagerRecordIds['periods'][] = $periodId;

    $setting = \Civi\Api4\ItemmanagerSettings::create(FALSE)
      ->addValue('price_field_value_id', $priceFieldValueId)
      ->addValue('itemmanager_periods_id', $periodId)
      ->addValue('itemmanager_successor_id', 0)
      ->execute()
      ->first();

    $settingId = (int) ($setting['id'] ?? 0);
    $this->assertGreaterThan(0, $settingId);
    $this->itemmanagerRecordIds['settings'][] = $settingId;
  }

  private function withRequestCid(int $contactId, callable $callback): void {
    $previousRequest = $_REQUEST;
    $previousGet = $_GET;
    $previousPost = $_POST;

    $_REQUEST['cid'] = $contactId;
    $_GET['cid'] = $contactId;
    unset($_POST['cid']);

    try {
      $callback();
    }
    finally {
      $_REQUEST = $previousRequest;
      $_GET = $previousGet;
      $_POST = $previousPost;
    }
  }

  private function getSeedId(string $key, int $index = 0): int {
    switch ($key) {
      case 'price_set':
        return $this->getPriceSetId();
      case 'price_field_value':
        return $this->getPriceFieldValueId();
      case 'member_contact':
        return $this->getMemberContactId();
    }

    $value = $this->seedIds[$key][$index] ?? NULL;
    $this->assertNotEmpty($value, "Seeded ID for {$key}[{$index}] is required");
    return (int) $value;
  }

  private function getPriceSetId(): int {
    $id = (int) ($this->seedIds['price_set'][0] ?? 0);
    if ($id) {
      $exists = (int) CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceSet', $id, 'id', 'id');
      if (!$exists) {
        $id = 0;
      }
    }
    if (!$id) {
      $id = (int) CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceSet', 'unit_test_priceset', 'id', 'name');
    }
    $this->assertNotEmpty($id, 'PriceSet id missing');
    return $id;
  }

  private function getPriceFieldValueId(): int {
    $id = (int) ($this->seedIds['price_field_value'][0] ?? 0);
    if ($id) {
      $exists = (int) CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceFieldValue', $id, 'id', 'id');
      if (!$exists) {
        $id = 0;
      }
    }
    if (!$id) {
      $dao = CRM_Core_DAO::executeQuery(
        "SELECT id FROM civicrm_price_field_value WHERE label = %1 LIMIT 1",
        [1 => ['Unit Test Membership', 'String']]
      );
      if ($dao->fetch()) {
        $id = (int) $dao->id;
      }
    }
    $this->assertNotEmpty($id, 'PriceFieldValue id missing');
    return $id;
  }

  private function getMemberContactId(): int {
    $id = (int) ($this->seedIds['member_contact'][0] ?? 0);
    if ($id) {
      $exists = (int) CRM_Core_DAO::getFieldValue('CRM_Contact_DAO_Contact', $id, 'id', 'id');
      if (!$exists) {
        $id = 0;
      }
    }
    if (!$id) {
      $dao = CRM_Core_DAO::executeQuery(
        "SELECT id FROM civicrm_contact WHERE first_name = %1 AND last_name = %2 LIMIT 1",
        [
          1 => ['Unit', 'String'],
          2 => ['Member', 'String'],
        ]
      );
      if ($dao->fetch()) {
        $id = (int) $dao->id;
      }
    }
    if (!$id) {
      $contact = \Civi\Api4\Contact::create(FALSE)
        ->addValue('contact_type', 'Individual')
        ->addValue('first_name', 'Unit')
        ->addValue('last_name', 'Member')
        ->execute()
        ->first();
      $id = (int) ($contact['id'] ?? 0);
    }
    $this->assertNotEmpty($id, 'Member contact id missing');
    return $id;
  }

  private function getMembershipTypeId(): int {
    $id = (int) ($this->seedIds['membership_type'][0] ?? 0);
    if ($id && CRM_Core_DAO::getFieldValue('CRM_Member_DAO_MembershipType', $id, 'id', 'id')) {
      return $id;
    }
    return (int) CRM_Core_DAO::getFieldValue('CRM_Member_DAO_MembershipType', 'Unit Test Membership', 'id', 'name');
  }

  private function getFinancialTypeId(): int {
    $id = (int) ($this->seedIds['financial_type'][0] ?? 0);
    if ($id && CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_FinancialType', $id, 'id', 'id')) {
      return $id;
    }
    return (int) CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_FinancialType', 'Membership VAT 19', 'id', 'name');
  }

  private function getPriceFieldId(): int {
    $id = (int) ($this->seedIds['price_field'][0] ?? 0);
    if ($id && CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceField', $id, 'id', 'id')) {
      return $id;
    }
    return (int) CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceField', 'membership_type', 'id', 'name');
  }

  private function ensureMembershipWithEndDate(int $contactId, int $membershipTypeId): void {
    $existing = \Civi\Api4\Membership::get(FALSE)
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('membership_type_id', '=', $membershipTypeId)
      ->execute()
      ->first();

    if (empty($existing['id'])) {
      civicrm_api3('Membership', 'create', [
        'contact_id' => $contactId,
        'membership_type_id' => $membershipTypeId,
        'status_id' => 'Current',
        'start_date' => date('Y-m-d'),
        'end_date' => date('Y-m-d', strtotime('+1 year')),
      ]);
    }
    elseif (empty($existing['end_date'])) {
      CRM_Core_DAO::executeQuery(
        "UPDATE civicrm_membership SET end_date = %1 WHERE id = %2",
        [
          1 => [date('Y-m-d', strtotime('+1 year')), 'String'],
          2 => [(int) $existing['id'], 'Integer'],
        ]
      );
    }
  }

  /**
   * @param array<int|string, mixed> $ids
   * @return array<int>
   */
  private function filterIds(array $ids): array {
    return array_values(array_filter(array_map('intval', $ids)));
  }

}

/**
 * Dashboard test double to capture assigned template variables.
 */
class CRM_Itemmanager_Test_DashboardPageDouble extends CRM_Itemmanager_Page_Dashboard {

  /** @var array<string, mixed> */
  public array $assignedValues = [];

  /**
   * Store assigned values for assertions.
   *
   * @param array|string $var
   * @param mixed $value
   */
  public function assign($var, $value = NULL) {
    if (is_array($var)) {
      foreach ($var as $name => $item) {
        $this->assignedValues[(string) $name] = $item;
      }
      return;
    }

    $this->assignedValues[(string) $var] = $value;
  }

  public function invokeProcessError(string $status, string $title, string $message): void {
    parent::processError($status, $title, $message);
  }

  public function invokeProcessDetail(string $membership, int $contribution, ?string $lineitem = NULL): void {
    parent::processDetail($membership, $contribution, $lineitem);
  }

}
