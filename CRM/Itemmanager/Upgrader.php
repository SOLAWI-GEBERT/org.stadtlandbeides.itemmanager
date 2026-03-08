
<?php
use CRM_Itemmanager_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Itemmanager_Upgrader extends CRM_Extension_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Example: Run an external SQL script when the module is installed.
   *
  public function install() {
    $this->executeSqlFile('sql/myinstall.sql');
  }

  /**
   * Example: Work with entities usually not available during the install step.
   *
   * This method can be used for any post-install tasks. For example, if a step
   * of your installation depends on accessing an entity that is itself
   * created during the installation (e.g., a setting or a managed entity), do
   * so here to avoid order of operation problems.
   */
  // public function postInstall() {
  //  $customFieldId = civicrm_api3('CustomField', 'getvalue', array(
  //    'return' => array("id"),
  //    'name' => "customFieldCreatedViaManagedHook",
  //  ));
  //  civicrm_api3('Setting', 'create', array(
  //    'myWeirdFieldSetting' => array('id' => $customFieldId, 'weirdness' => 1),
  //  ));
  // }

  /**
   * Example: Run an external SQL script when the module is uninstalled.
   */
  // public function uninstall() {
  //  $this->executeSqlFile('sql/myuninstall.sql');
  // }

  /**
   * Example: Run a simple query when a module is enabled.
   */
  // public function enable() {
  //  CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 1 WHERE bar = "whiz"');
  // }

  /**
   * Example: Run a simple query when a module is disabled.
   */
  // public function disable() {
  //   CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 0 WHERE bar = "whiz"');
  // }

  /**
   * Example: Run a couple simple queries.
   *
   * @return TRUE on success
   * @throws Exception
   */
  // public function upgrade_4200() {
  //   $this->ctx->log->info('Applying update 4200');
  //   CRM_Core_DAO::executeQuery('UPDATE foo SET bar = "whiz"');
  //   CRM_Core_DAO::executeQuery('DELETE FROM bang WHERE willy = wonka(2)');
  //   return TRUE;
  // }


  /**
   * Example: Run an external SQL script.
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_4000() {
     $this->ctx->log->info('Applying update 4000');
     // this path is relative to the extension base dir
     $this->executeSqlFile('sql/upgrade_4000.sql');
     return TRUE;
  }


    /**
     * Example: Run an external SQL script.
     *
     * @return TRUE on success
     * @throws Exception
     */
    public function upgrade_4100() {
        $this->ctx->log->info('Applying update 4100');
        // this path is relative to the extension base dir
        $this->executeSqlFile('sql/upgrade_4100.sql');
        return TRUE;
    }

    /**
     * Example: Run an external SQL script.
     *
     * @return TRUE on success
     * @throws Exception
     */
    public function upgrade_4200() {
        $this->ctx->log->info('Applying update 4200');
        // this path is relative to the extension base dir
        $this->executeSqlFile('sql/upgrade_4200.sql');
        return TRUE;
    }

    /**
     * Example: Run an external SQL script.
     *
     * @return TRUE on success
     * @throws Exception
     */
    public function upgrade_4210() {
        $this->ctx->log->info('Applying update 4210');
        // this path is relative to the extension base dir
        $this->executeSqlFile('sql/upgrade_4210.sql');
        return TRUE;
    }

    /**
     * Example: Run an external SQL script.
     *
     * @return TRUE on success
     * @throws Exception
     */
    public function upgrade_4300() {
        $this->ctx->log->info('Applying update 4300');
        // this path is relative to the extension base dir
        $this->executeSqlFile('sql/upgrade_4300.sql');
        return TRUE;
    }

    /**
     * Integrate line item editor: generate additional price fields if not
     * already present (e.g. from a previous lineitemedit installation) and
     * update contribution net_amount where fee_amount is set.
     *
     * @return TRUE on success
     * @throws Exception
     */
    public function upgrade_4400() {
        $this->ctx->log->info('Applying update 4400 - Integrate line item editor');
        $this->addTask(
            E::ts('Migrate managed entities from lineitemedit'),
            'migrateLineitemeditManagedEntities'
        );
        $this->addTask(
            E::ts('Generate additional line item price fields'),
            'generateLineItemPriceFields'
        );
        $this->addTask(
            E::ts('Update contribution net_amount'),
            'updateContributionNetAmount'
        );
        return TRUE;
    }

    /**
     * Re-assign managed entities that were previously owned by
     * biz.jmaconsulting.lineitemedit to this extension, and remove
     * orphaned SavedSearch/SearchDisplay records so our managed
     * declarations can recreate them cleanly.
     */
    public function migrateLineitemeditManagedEntities() {
        // Re-assign any managed records from the old module to ours
        CRM_Core_DAO::executeQuery(
            "UPDATE civicrm_managed SET module = %1
             WHERE module = 'biz.jmaconsulting.lineitemedit'",
            [1 => [E::LONG_NAME, 'String']]
        );

        // Remove managed records that reference our SearchDisplay names
        // so the managed system can recreate them fresh
        $names = [
            'SavedSearch_Line_Items_SearchDisplay_Contribution_Amount_Tax',
            'SavedSearch_Line_Items_SearchDisplay_Line_Items_Table',
            'Line_Items',
        ];
        foreach ($names as $name) {
            CRM_Core_DAO::executeQuery(
                "DELETE FROM civicrm_managed WHERE module = %1 AND name = %2",
                [
                    1 => [E::LONG_NAME, 'String'],
                    2 => [$name, 'String'],
                ]
            );
        }

        // Drop existing SearchDisplay records that conflict
        CRM_Core_DAO::executeQuery(
            "DELETE FROM civicrm_search_display WHERE name IN ('Line_Items_Table_Tax', 'Line_Items_Table')
             AND saved_search_id IN (SELECT id FROM civicrm_saved_search WHERE name = 'Line_Items')"
        );

        // Drop the existing SavedSearch if it exists
        CRM_Core_DAO::executeQuery(
            "DELETE FROM civicrm_saved_search WHERE name = 'Line_Items'"
        );

        $this->ctx->log->info('Migrated/cleaned lineitemedit managed entities');
        return TRUE;
    }

    /**
     * Generate additional price fields for the line item editor,
     * only if they do not already exist.
     */
    public function generateLineItemPriceFields() {
        // Check if additional price fields already exist (from previous lineitemedit install)
        $defaultPriceSetId = CRM_Core_DAO::singleValueQuery(
            "SELECT id FROM civicrm_price_set WHERE name = 'default_contribution_amount' LIMIT 1"
        );
        if (!$defaultPriceSetId) {
            $this->ctx->log->info('No default_contribution_amount price set found, skipping price field generation');
            return TRUE;
        }

        $existingAdditionalFields = CRM_Core_DAO::singleValueQuery(
            "SELECT COUNT(*) FROM civicrm_price_field
             WHERE price_set_id = %1 AND label LIKE 'Additional Line Item%%'",
            [1 => [$defaultPriceSetId, 'Integer']]
        );

        if ($existingAdditionalFields > 0) {
            $this->ctx->log->info(
                "Found {$existingAdditionalFields} existing additional price fields, skipping generation"
            );
            return TRUE;
        }

        $this->ctx->log->info('Generating additional line item price fields');
        CRM_Itemmanager_Util_LineItemEditor::generatePriceField();
        return TRUE;
    }

    /**
     * Update contribution net_amount where fee_amount is set but net_amount
     * was not calculated. Ported from lineitemedit upgrade_2400.
     */
    public function updateContributionNetAmount() {
        CRM_Core_DAO::executeQuery(
            'UPDATE civicrm_contribution SET net_amount = total_amount - fee_amount
             WHERE fee_amount IS NOT NULL AND fee_amount > 0 AND (net_amount IS NULL OR net_amount = 0)'
        );
        return TRUE;
    }

  /**
   * Example: Run a slow upgrade process by breaking it up into smaller chunk.
   *
   * @return TRUE on success
   * @throws Exception
   */
  // public function upgrade_4202() {
  //   $this->ctx->log->info('Planning update 4202'); // PEAR Log interface

  //   $this->addTask(E::ts('Process first step'), 'processPart1', $arg1, $arg2);
  //   $this->addTask(E::ts('Process second step'), 'processPart2', $arg3, $arg4);
  //   $this->addTask(E::ts('Process second step'), 'processPart3', $arg5);
  //   return TRUE;
  // }
  // public function processPart1($arg1, $arg2) { sleep(10); return TRUE; }
  // public function processPart2($arg3, $arg4) { sleep(10); return TRUE; }
  // public function processPart3($arg5) { sleep(10); return TRUE; }

  /**
   * Example: Run an upgrade with a query that touches many (potentially
   * millions) of records by breaking it up into smaller chunks.
   *
   * @return TRUE on success
   * @throws Exception
   */
  // public function upgrade_4203() {
  //   $this->ctx->log->info('Planning update 4203'); // PEAR Log interface

  //   $minId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(min(id),0) FROM civicrm_contribution');
  //   $maxId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(max(id),0) FROM civicrm_contribution');
  //   for ($startId = $minId; $startId <= $maxId; $startId += self::BATCH_SIZE) {
  //     $endId = $startId + self::BATCH_SIZE - 1;
  //     $title = E::ts('Upgrade Batch (%1 => %2)', array(
  //       1 => $startId,
  //       2 => $endId,
  //     ));
  //     $sql = '
  //       UPDATE civicrm_contribution SET foobar = whiz(wonky()+wanker)
  //       WHERE id BETWEEN %1 and %2
  //     ';
  //     $params = array(
  //       1 => array($startId, 'Integer'),
  //       2 => array($endId, 'Integer'),
  //     );
  //     $this->addTask($title, 'executeSql', $sql, $params);
  //   }
  //   return TRUE;
  // }

}
