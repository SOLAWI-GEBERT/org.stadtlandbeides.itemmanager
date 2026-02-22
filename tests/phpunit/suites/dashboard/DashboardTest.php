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

  public function setUp(): void {
    parent::setUp();

    $this->itemmanagerRecordIds = [
      'periods' => [],
      'settings' => [],
    ];
    $this->createdContactIds = [];
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
    $this->assertNotEmpty($memberArray['values'] ?? []);

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
    $this->assertNotEmpty($memberList, 'Expected dashboard member list to contain seeded membership data');

    $firstMember = reset($memberList);
    $this->assertIsArray($firstMember);
    $this->assertArrayHasKey('field_data', $firstMember);
    $this->assertArrayHasKey('member_name', $firstMember);
    $this->assertArrayHasKey('status', $firstMember);
    $this->assertArrayHasKey('active', $firstMember);

    $this->assertNotEmpty($firstMember['member_name']);
    $this->assertNotEmpty($firstMember['status']);

    $fieldData = $firstMember['field_data'];
    $this->assertIsArray($fieldData);
    $this->assertNotEmpty($fieldData);

    $firstFieldGroup = reset($fieldData);
    $this->assertIsArray($firstFieldGroup);
    $firstQuantityGroup = reset($firstFieldGroup);
    $this->assertIsArray($firstQuantityGroup);

    $this->assertArrayHasKey('item_quantity', $firstQuantityGroup);
    $this->assertArrayHasKey('item_label', $firstQuantityGroup);
    $this->assertArrayHasKey('item_dates', $firstQuantityGroup);
    $this->assertArrayHasKey('min', $firstQuantityGroup);
    $this->assertArrayHasKey('max', $firstQuantityGroup);
    $this->assertNotEmpty($firstQuantityGroup['item_dates']);
    $this->assertNotEmpty($firstQuantityGroup['min']);
    $this->assertNotEmpty($firstQuantityGroup['max']);
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
    $value = $this->seedIds[$key][$index] ?? NULL;
    $this->assertNotEmpty($value, "Seeded ID for {$key}[{$index}] is required");
    return (int) $value;
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
