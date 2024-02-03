<?php

/**
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 *
 * Generated from org.stadtlandbeides.itemmanager/xml/schema/CRM/Itemmanager/ItemmanagerPeriods.xml
 * DO NOT EDIT.  Generated by CRM_Core_CodeGen
 * (GenCodeChecksum:a7e7f6d51d44fa716d0747fe1e67071b)
 */

/**
 * Database access object for the ItemmanagerPeriods entity.
 */
class CRM_Itemmanager_DAO_ItemmanagerPeriods extends CRM_Core_DAO {

  /**
   * Static instance to hold the table name.
   *
   * @var string
   */
  public static $_tableName = 'civicrm_itemmanager_periods';

  /**
   * Should CiviCRM log any modifications to this table in the civicrm_log table.
   *
   * @var bool
   */
  public static $_log = TRUE;

  /**
   * Unique ItemmanagerPeriods ID
   *
   * @var int
   */
  public $id;

  /**
   * FK to civicrm_price_set
   *
   * @var int
   */
  public $price_set_id;

  /**
   * If non-zero, do not show this field before the date specified
   *
   * @var date
   */
  public $period_start_on;

  /**
   * Number of periods at start
   *
   * @var int
   */
  public $periods;

  /**
   * Period interval type
   *
   * @var int
   */
  public $period_type;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->__table = 'civicrm_itemmanager_periods';
    parent::__construct();
  }

  /**
   * Returns foreign keys and entity references.
   *
   * @return array
   *   [CRM_Core_Reference_Interface]
   */
  public static function getReferenceColumns() {
    if (!isset(Civi::$statics[__CLASS__]['links'])) {
      Civi::$statics[__CLASS__]['links'] = static::createReferenceColumns(__CLASS__);
      Civi::$statics[__CLASS__]['links'][] = new CRM_Core_Reference_Basic(self::getTableName(), 'price_set_id', 'civicrm_price_set', 'id');
      CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'links_callback', Civi::$statics[__CLASS__]['links']);
    }
    return Civi::$statics[__CLASS__]['links'];
  }

    /**
     * Returns localized title of this entity.
     */
    public static function getEntityTitle() {
        return ts('Itemmanager Periods');
    }


  /**
   * Returns all the column names of this table
   *
   * @return array
   */
  public static function &fields() {
    if (!isset(Civi::$statics[__CLASS__]['fields'])) {
      Civi::$statics[__CLASS__]['fields'] = [
        'id' => [
          'name' => 'id',
          'type' => CRM_Utils_Type::T_INT,
          'description' => CRM_Itemmanager_ExtensionUtil::ts('Unique ItemmanagerPeriods ID'),
          'required' => TRUE,
          'where' => 'civicrm_itemmanager_periods.id',
          'table_name' => 'civicrm_itemmanager_periods',
          'entity' => 'ItemmanagerPeriods',
          'bao' => 'CRM_Itemmanager_DAO_ItemmanagerPeriods',
          'localizable' => 0,
        ],
        'price_set_id' => [
          'name' => 'price_set_id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => CRM_Itemmanager_ExtensionUtil::ts('Price Set'),
          'description' => CRM_Itemmanager_ExtensionUtil::ts('FK to civicrm_price_set'),
          'where' => 'civicrm_itemmanager_periods.price_set_id',
          'table_name' => 'civicrm_itemmanager_periods',
          'entity' => 'ItemmanagerPeriods',
          'bao' => 'CRM_Itemmanager_DAO_ItemmanagerPeriods',
          'localizable' => 0,
          'FKClassName' => 'CRM_Price_DAO_PriceSet',
        ],
        'period_start_on' => [
          'name' => 'period_start_on',
          'type' => CRM_Utils_Type::T_DATE,
          'title' => CRM_Itemmanager_ExtensionUtil::ts('Booking Period Start on'),
          'description' => CRM_Itemmanager_ExtensionUtil::ts('If non-zero, do not show this field before the date specified'),
          'import' => TRUE,
          'where' => 'civicrm_itemmanager_periods.period_start_on',
          'headerPattern' => '/^join|(j(oin\s)?date)$/i',
          'dataPattern' => '/\d{4}-?\d{2}-?\d{2}/',
          'export' => TRUE,
          'table_name' => 'civicrm_itemmanager_periods',
          'entity' => 'ItemmanagerPeriods',
          'bao' => 'CRM_Itemmanager_DAO_ItemmanagerPeriods',
          'localizable' => 0,
          'html' => [
            'type' => 'Select Date',
            'formatType' => 'activityDate',
          ],
        ],
        'periods' => [
          'name' => 'periods',
          'type' => CRM_Utils_Type::T_INT,
          'title' => CRM_Itemmanager_ExtensionUtil::ts('Periods'),
          'description' => CRM_Itemmanager_ExtensionUtil::ts('Number of periods at start'),
          'where' => 'civicrm_itemmanager_periods.periods',
          'default' => 'NULL',
          'table_name' => 'civicrm_itemmanager_periods',
          'entity' => 'ItemmanagerPeriods',
          'bao' => 'CRM_Itemmanager_DAO_ItemmanagerPeriods',
          'localizable' => 0,
          'html' => [
            'type' => 'Text',
          ],
        ],
        'period_type' => [
          'name' => 'period_type',
          'type' => CRM_Utils_Type::T_INT,
          'title' => CRM_Itemmanager_ExtensionUtil::ts('Period Type'),
          'description' => CRM_Itemmanager_ExtensionUtil::ts('Period interval type'),
          'where' => 'civicrm_itemmanager_periods.period_type',
          'default' => 'NULL',
          'table_name' => 'civicrm_itemmanager_periods',
          'entity' => 'ItemmanagerPeriods',
          'bao' => 'CRM_Itemmanager_DAO_ItemmanagerPeriods',
          'localizable' => 0,
          'html' => [
            'type' => 'Text',
          ],
        ],
      'id' => [
          'name' => 'itemmanager_period_successor_id',
          'type' => CRM_Utils_Type::T_INT,
          'description' => CRM_Itemmanager_ExtensionUtil::ts('Successor ItemmanagerPeriods ID'),
          'required' => FALSE,
          'where' => 'civicrm_itemmanager_periods.itemmanager_period_successor_id',
          'table_name' => 'civicrm_itemmanager_periods',
          'entity' => 'ItemmanagerPeriods',
          'bao' => 'CRM_Itemmanager_DAO_ItemmanagerPeriods',
          'localizable' => 0,
      ],
      ];
      CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'fields_callback', Civi::$statics[__CLASS__]['fields']);
    }
    return Civi::$statics[__CLASS__]['fields'];
  }

  /**
   * Return a mapping from field-name to the corresponding key (as used in fields()).
   *
   * @return array
   *   Array(string $name => string $uniqueName).
   */
  public static function &fieldKeys() {
    if (!isset(Civi::$statics[__CLASS__]['fieldKeys'])) {
      Civi::$statics[__CLASS__]['fieldKeys'] = array_flip(CRM_Utils_Array::collect('name', self::fields()));
    }
    return Civi::$statics[__CLASS__]['fieldKeys'];
  }

  /**
   * Returns the names of this table
   *
   * @return string
   */
  public static function getTableName() {
    return self::$_tableName;
  }

  /**
   * Returns if this table needs to be logged
   *
   * @return bool
   */
  public function getLog() {
    return self::$_log;
  }

  /**
   * Returns the list of fields that can be imported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &import($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getImports(__CLASS__, 'itemmanager_periods', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of fields that can be exported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &export($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getExports(__CLASS__, 'itemmanager_periods', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of indices
   *
   * @param bool $localize
   *
   * @return array
   */
  public static function indices($localize = TRUE) {
    $indices = [];
    return ($localize && !empty($indices)) ? CRM_Core_DAO_AllCoreTables::multilingualize(__CLASS__, $indices) : $indices;
  }

}
