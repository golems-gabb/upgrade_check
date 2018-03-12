<?php

namespace Upgrade_check;

require_once __DIR__ . '/EvaluationCode.php';

use Upgrade_check\EvaluationCode;

class EvaluationImplementation {

  private $moduleName = 'upgrade_check';

  /**
   * Implements upgrade_check_form().
   */
  public static function upgradeCheckForm() {
    $form = array();
    $form['analyze'] = array(
      '#type' => 'fieldset',
      '#title' => t('Analyze'),
    );
    $text = 'Welcome to upgrade check per Drupal 8. ';
    $text .= 'Click on the link to get the upgrade score of your webresource.';
    $form['analyze']['description'] = array(
      '#type' => 'item',
      '#value' => t('!text', array('!text' => $text)),
    );
    $form['analyze'][UPGRADE_CHECK_DATA_METHOD] = array(
      '#type' => 'radios',
      '#title' => t('Data transfer method'),
      '#description' => t('Depending on the method selected, the method for data transfer for analysis will be changed.'),
      '#options' => array(
        'manual' => t('Manual'),
        'semiautomatic' => t('Semiautomatic'),
        'automatic' => t('Automatic'),
      ),
      '#disabled' => 'disabled',
      '#default_value' => variable_get(UPGRADE_CHECK_DATA_METHOD, 'manual'),
    );
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => 'Analyze',
    );
    return $form;
  }

  /**
   * Implements _upgrade_check_json_form().
   */
  public static function upgradeCheckJsonForm() {
    $form = array();
    if (file_exists(variable_get(UPGRADE_CHECK_JSON_PATH, NULL))) {
      $form['download'] = array(
        '#type' => 'fieldset',
        '#title' => t('Download JSON file'),
      );
      $options = array(
        'absolute' => TRUE,
        'html' => TRUE,
        'attributes' => array('target' => '_blank'),
      );
      $link = l('Upload Json', UPGRADE_CHECK_URL, $options);
      $form['download']['description'] = array(
        '#type' => 'item',
        '#value' => t('Please follow the steps to complete migration check process:'),
      );
      $form['download']['description_list_one'] = array(
        '#type' => 'item',
        '#value' => t('I - Download Json File from the given below link.'),
      );
      $form['download']['description_list_two'] = array(
        '#type' => 'item',
        '#value' => t('II - Upload the json file here --> !link',
          array(
            '!link' => $link,
          )
        ),
      );
      $form['submit'] = array(
        '#type' => 'submit',
        '#value' => 'Download JSON',
      );
    }
    else {
      $text = 'Their is no json file to download. ';
      $text .= 'Please create one by clicking the below link!';
      drupal_set_message(t('!text', array('!text' => $text)));
      drupal_goto(UPGRADE_CHECK_EVALUATION);
    }
    return $form;
  }

  /**
   * Implements _upgrade_check_settings_form().
   */
  public static function upgradeCheckSettingsForm() {
    $form[UPGRADE_CHECK_SETTINGS_FORM] = array(
      '#type' => 'fieldset',
      '#title' => t('Settings'),
    );
    $form[UPGRADE_CHECK_SETTINGS_FORM][UPGRADE_CHECK_REPLACE_ENTITY_NAME] = array(
      '#type' => 'radios',
      '#title' => t('Replace enity names'),
      '#options' => array('no' => t('No'), 'yes' => t('Yes')),
      '#default_value' => variable_get(UPGRADE_CHECK_REPLACE_ENTITY_NAME, 'no'),
    );
    $form[UPGRADE_CHECK_SETTINGS_FORM][UPGRADE_CHECK_SALT_FIELD_NAME] = array(
      '#type' => 'textfield',
      '#title' => t('Default salt'),
      '#description' => t('Automatic salt generation to encrypt real entity names.'),
      '#disabled' => 'disabled',
      '#default_value' => variable_get(UPGRADE_CHECK_SALT_FIELD_NAME, md5(uniqid(rand()))),
      '#states' => array(
        'visible' => array(
          ':input[name="' . UPGRADE_CHECK_REPLACE_ENTITY_NAME . '"]' => array(
            'value' => 'yes',
          ),
        ),
      ),
    );
    return system_settings_form($form);
  }

  /**
   * Implements upgrade_check_form_submit().
   */
  public static function upgradeCheckFormSubmit() {
    global $base_url;
    $data = $operations = array();
    $evIm = new EvaluationImplementation;
    $data['site_info'] = array(
      'site_name' => $evIm->generateCryptName(variable_get('site_name', 'Drupal')),
      'base_url' => $base_url,
      'core_version' => VERSION,
    );
    $evIm->upgradeCheckEntityData($data);
    $evIm->upgradeCheckModulesData($operations);
    $evIm->upgradeCheckThemesData($operations);
    $data['fields_data'] = $evIm->upgradeCheckFieldsData();
    $data['nodes_data'] = $evIm->upgradeCheckNodesData();
    $data['menu_data'] = $evIm->upgradeCheckMenusData();
    $data['taxonomy_data'] = $evIm->upgradeCheckTaxonomyData();
    $data['views_data'] = $evIm->upgradeCheckViewsData();
    $operations[] = array('_upgrade_check_create_json', array('data' => $data));
    $batch = array(
      'operations' => $operations,
      'finished' => '_upgrade_check_json_finished',
      'title' => t('Analyzing your webresource to get Upgrade Score'),
      'init_message' => t('File Processing Starts'),
      'progress_message' => t('Analyzing Your webresource...'),
      'error_message' => t('An error has occurred. Please try again'),
    );
    batch_set($batch);
    return FALSE;
  }

  /**
   * Implements _upgrade_check_themes_evaluation().
   */
  public static function upgradeCheckThemesEvaluation($theme, &$context) {
    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
    }
    $evaluationCode = new EvaluationCode;
    $themes = $evaluationCode->themesEvaluation($theme);
    $context['results']['themes'][] = $themes;
    $context['sandbox']['progress']++;
    return '';
  }

  /**
   * Implements _upgrade_check_modules_evaluation().
   */
  public static function upgradeCheckModulesEvaluation($module, &$context) {
    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
    }
    $evaluationCode = new EvaluationCode;
    $modules = $evaluationCode->modulesEvaluation($module);
    $context['results']['modules'][] = $modules;
    $context['sandbox']['progress']++;
    return '';
  }

  /**
   * Fetching: Nodes/Files usage/Users/Image styles/Roles/Languages/Blocks.
   */
  private function upgradeCheckEntityData(&$data) {
    $keys = array(
      'nodes_count' => array('node', 'nid', 'n'),
      'existing_files_count' => array('file_usage', 'fid', 'f'),///////////////////////
      'users_count' => array('users', 'uid', 'u'),
      'image_styles_count' => array('image_styles', 'isid', 'i'),///////////////////////
      'roles_count' => array('users_roles', 'rid', 'u'),
      'languages_count' => array('languages', 'language', 'l'),//////////////////////
      'block_custom_count' => array('block_custom', 'bid', 'b'),////////////////////
    );
    foreach ($keys as $key => $val) {
      $param = array('t' => $val[0], 'a' => $val[2], 'f' => array($val[1]));
      $result = $this->generateSql($param);
      $data[$key] = count($result);
    }
    return NULL;
  }

  /**
   * Fetch menus data.
   */
  private function upgradeCheckMenusData() {
    $result = array();
    $param = array(
      't' => 'menu_links',
      'a' => 'm',
      'f' => array('menu_name'),
    );
    $links = $this->generateSql($param);
    foreach ($links as $link) {
      $menuName = $this->generateCryptName($link->menu_name);
      if (empty($result[$menuName])) {
        $result[$menuName] = 1;
      }
      else {
        ++$result[$menuName];
      }
    }
    return $result;
  }

  /**
   * Fetch nodes data.
   */
  private function upgradeCheckNodesData() {
    $result = array();
    $param = array(
      't' => 'node_type',
      'a' => 'n',
      'f' => array('type', 'module'),
    );
    $nodes = $this->generateSql($param);
    foreach ($nodes as $node) {
      $result[$this->generateCryptName($node->type)] = $node->module;
    }
    return $result;
  }

  /**
   * Fetch fields data.
   */
  private function upgradeCheckFieldsData() {
    $param = array(
      't' => 'field_config_instance',/////////////////////////////
      'a' => 'fci',
      'f' => array('entity_type', 'bundle'),
      'j' => array(
        't' => 'field_config',
        'a' => 'fc',
        'f' => array('type', 'module', 'active'),
        'con' => array('left' => 'field_id', 'right' => 'id'),
        'jt' => 'left',
      ),
    );
    $result = $this->generateSql($param);
    if (!empty($result)) {
      foreach ($result as $key => $value) {
        if (!empty($value) && !empty($value->bundle)) {
          $result[$key]->bundle = $this->generateCryptName($value->bundle);
        }
      }
    }
    return $result;
  }

  /**
   * Fetch taxonomy data.
   */
  private function upgradeCheckTaxonomyData() {
    $result = array();
    $taxonomyVocabulary = $this->upgradeCheckTaxonomyVocabularyData();
    $taxonomyTerms = $this->upgradeCheckTaxonomyTermsData();
    if (!empty($taxonomyTerms) && !empty($taxonomyVocabulary)) {
      foreach ($taxonomyTerms as $key => $value) {
        if (!empty($taxonomyVocabulary[$value])) {
          $vocabularyName = $this->generateCryptName($taxonomyVocabulary[$value]);
          if (empty($result[$vocabularyName])) {
            $result[$vocabularyName] = 1;
          }
          else {
            ++$result[$vocabularyName];
          }
        }
      }
    }
    return $result;
  }

  /**
   * Fetch taxonomy vocabulary data.
   */
  private function upgradeCheckTaxonomyVocabularyData() {
    $result = array();
    $param = array(
      't' => 'taxonomy_vocabulary',////////////
      'a' => 't',
      'f' => array('vid', 'machine_name'),
    );
    $vocabularies = $this->generateSql($param);
    foreach ($vocabularies as $vocabulary) {
      $result[$vocabulary->vid] = $vocabulary->machine_name;
    }
    return $result;
  }

  /**
   * Fetch taxonomy terms data.
   */
  private function upgradeCheckTaxonomyTermsData() {
    $result = array();
    $param = array(
      't' => 'taxonomy_term_data',////////////////
      'a' => 't',
      'f' => array('tid', 'vid'),
    );
    $terms = $this->generateSql($param);
    foreach ($terms as $term) {
      $result[$term->tid] = $term->vid;
    }
    return $result;
  }

  /**
   * Fetch data of all enabled modules.
   */
  private function upgradeCheckModulesData(&$operations) {
    $system = EvaluationCode::upgradeCheckSubmodules($this->systemList('module_enabled'));
    foreach ($system as $module) {
      if (!empty($module->name) && $module->name !== $this->moduleName) {
        $operations[] = array(
          '_upgrade_check_modules_evaluation',
          array('module' => (array) $module),
        );
      }
    }
    return NULL;
  }

  /**
   * Fetch data of all enabled themes.
   */
  private function upgradeCheckThemesData(&$operations) {
    $themes = $this->systemList('theme');
    foreach ($themes as $theme) {
      $operations[] = array(
        '_upgrade_check_themes_evaluation',
        array('theme' => (array) $theme),
      );
    }
    return NULL;
  }

  /**
   * Fetching views data.
   */
  private function upgradeCheckViewsData() {
    $viewsdata = array();
    if (module_exists('views')) {
      $param = array(
        't' => 'views_view',
        'a' => 'v',
        'f' => array('vid', 'name', 'description'),
      );
      $query = $this->generateSql($param, TRUE);
      foreach ($query as $view) {
        $param = array(
          't' => 'views_display',
          'a' => 'v',
          'f' => array('id', 'display_title'),
          'c' => array(array('f' => 'vid', 'v' => $view->vid)),
        );
        $display_count = $this->generateSql($param);
        array_push($viewsdata, array(
          'view' => $this->generateCryptName($view->name),
          'description' => $view->description,
          'displays' => count($display_count),
        ));
      }
    }
    return $viewsdata;
  }

  /**
   * Generate SQL.
   */
  private function generateSql($data, $dontAll = FALSE) {
    $result = array();
    $fields = $ident = $condition = '';
    if (!empty($data) && !empty($data['t']) && !empty($data['a'])) {
      if (!empty($data['j']) && !empty($data['j']['t'])) {
        if (!empty($data['j']['a']) && !empty($data['j']['jt']) && !empty($data['j']['con'])) {
          $sqlVal = $data['a'] . '.' . $data['j']['con']['left'] . ' = ';
          $sqlVal .= $data['j']['a'] . '.' . $data['j']['con']['right'];
          if ($data['j']['jt'] === 'inner') {
            $join = 'INNER';
          }
          elseif ($data['j']['jt'] === 'left') {
            $join = 'LEFT';
          }
          elseif ($data['j']['jt'] === 'right') {
            $join = 'RIGHT';
          }
          $joinSql = $join . ' JOIN {' . $data['j']['t'] . '} ' . $data['j']['a'] . ' ON ' . $sqlVal;
        }
        if (!empty($data['j']['f'])) {
          foreach ($data['f'] as $valFC) {
            $fields .= $ident . $data['j']['a'] . '.' . $valFC;
            $ident = ', ';
          }
        }
        if (!empty($data['j']['c']) && is_array($data['j']['c'])) {
          $ident = '';
          foreach ($data['j']['c'] as $value) {
            if (!empty($value) && !empty($value['f']) && !empty($value['v'])) {
              $param = '=';
              if (!empty($value['p'])) {
                $param = $value['p'];
              }
              if (!empty($conditions)) {
                $ident = ' AND ';
              }
              if (is_numeric($value['v'])) {
                $conditions .= $ident . $value['f'] . ' ' . $param . ' ' . $value['v'];
              }
              else {
                $conditions .= $ident . $value['f'] . ' ' . $param . ' "' . $value['v'] . '"';
              }
            }
          }
        }
      }
      if (!empty($data['f']) && is_array($data['f'])) {
        $ident = '';
        if (!empty($fields)) {
          $ident = ', ';
        }
        foreach ($data['f'] as $valF) {
          $fields .= $ident . $data['a'] . '.' . $valF;
          $ident = ', ';
        }
      }
      if (!empty($data['c']) && is_array($data['c'])) {
        $ident = '';
        foreach ($data['c'] as $value) {
          if (!empty($value) && !empty($value['f']) && !empty($value['v'])) {
            $param = '=';
            if (!empty($value['p'])) {
              $param = $value['p'];
            }
            if (!empty($conditions)) {
              $ident = ' AND ';
            }
            if (is_numeric($value['v'])) {
              $conditions .= $ident . $value['f'] . ' ' . $param . ' ' . $value['v'];
            }
            else {
              $conditions .= $ident . $value['f'] . ' ' . $param . ' "' . $value['v'] . '"';
            }
          }
        }
      }
      if (!empty($fields)) {
        $query = 'SELECT ' . $fields . ' FROM {' . $data['t'] . '} ' . $data['a'];
        if (!empty($joinSql)) {
          $query .= ' ' . $joinSql;
        }
        if (!empty($conditions)) {
          $query .= ' WHERE ' . $conditions;
        }
        if (empty($dontAll)) {
          $sql = db_query($query);
          while ($data = db_fetch_object($sql)) {
            if (!empty($data)) {
              $result[] = $data;
            }
          }
        }
        else {
          $result = db_query($query);
        }
      }
    }
    return $result;
  }

  /**
   * Implements upgrade_check_json_finished().
   */
  public static function upgradeCheckJsonFinished($success) {
    if ($success) {
      drupal_goto(UPGRADE_CHECK_JSON);
    }
    else {
      drupal_set_message(t('There were errors during creating json file.Please try again'));
    }
    return FALSE;
  }

  /**
   * Implements upgrade_check_create_json().
   */
  public static function upgradeCheckCreateJson($data, &$context) {
    $eC = new EvaluationCode;
    $response = array();
    $data['modules'] = $eC->upgradeCheckSubmodulesDeleteInfo($context['results']['modules']);
    $data['themes'] = $eC->upgradeCheckConvertAssociateArray($context['results']['themes']);
    $response['data'] = $data;
    $file_name = $response['data']['site_info']['site_name'] . '.' . 'json';
    $file_path = file_save_data(json_encode($response), $file_name, FILE_EXISTS_REPLACE);
    variable_set(UPGRADE_CHECK_JSON_PATH, $file_path);
    return FALSE;
  }

  /**
   * Implements _upgrade_check_json_form_submit().
   */
  public static function upgradeCheckJsonFormSubmit() {
    $siteName = variable_get('site_name', 'Drupal');
    $filePath = variable_get(UPGRADE_CHECK_JSON_PATH, NULL);
    $file = fopen($filePath, 'r') or die('Please give suitable Permission to files folder');
    $fileSize = filesize($filePath);
    header('Content-type: application/json');
    header('Content-Type: application/force-download');
    header('Content-Type: application/download');
    header('Content-Description: File Transfer');
    header('Content-Disposition: attachment;filename=' . $siteName . '.' . 'json');
    header('Content-length: ' . $fileSize);
    header('Cache-control: private'); //use this to open files directly
    while (!feof($file)) {
      $buffer = fread($file, 2048);
      echo $buffer;
      flush();
    }
    fclose($file);
    file_delete(variable_get(UPGRADE_CHECK_JSON_PATH, NULL));
    variable_del(UPGRADE_CHECK_JSON_PATH);
    exit();
  }

  /**
   * Crying values.
   */
  private function generateCryptName($name) {
    $crypt = variable_get(UPGRADE_CHECK_REPLACE_ENTITY_NAME, 'no');
    if (!empty($crypt) && $crypt === 'yes') {
      $salt = variable_get(UPGRADE_CHECK_SALT_FIELD_NAME, '');
      $name = md5($name . $salt);
    }
    return $name;
  }

  /**
   * Backport of the DBTNG system_list() from Drupal 7.
   */
  private function systemList($type) {
    $lists =& drupal_static(__FUNCTION__);
    // For bootstrap modules, attempt to fetch the list from cache if possible.
    // if not fetch only the required information to fire bootstrap hooks
    // in case we are going to serve the page from cache.
    if ($type == 'bootstrap') {
      if (isset($lists['bootstrap'])) {
        return $lists['bootstrap'];
      }
      if ($cached = cache_get('bootstrap_modules', 'cache_bootstrap')) {
        $bootstrap_list = $cached->data;
      }
      else {
        $query = db_query("SELECT name, filename FROM {system} WHERE status = 1 AND bootstrap = 1 AND type = 'module' ORDER BY weight ASC, name ASC");
        $bootstrap_list = $this->dbFetchAllAssoc($query, 'name');
        cache_set('bootstrap_modules', $bootstrap_list, 'cache_bootstrap');
      }
      // To avoid a separate database lookup for the filepath, prime the
      // drupal_get_filename() static cache for bootstrap modules only.
      // The rest is stored separately to keep the bootstrap module cache small.
      foreach ($bootstrap_list as $module) {
        drupal_get_filename('module', $module->name, $module->filename);
      }
      // We only return the module names here since module_list() doesn't need
      // the filename itself.
      $lists['bootstrap'] = array_keys($bootstrap_list);
    }
    elseif (!isset($lists['module_enabled'])) {
      if ($cached = cache_get('system_list', 'cache_bootstrap')) {
        $lists = $cached->data;
      }
      else {
        $lists = array(
          'module_enabled' => array(),
          'theme' => array(),
          'filepaths' => array(),
        );
        // The module name (rather than the filename) is used as the fallback
        // weighting in order to guarantee consistent behavior across different
        // Drupal installations, which might have modules installed in different
        // locations in the file system. The ordering here must also be
        // consistent with the one used in module_implements().
        $result = db_query("SELECT * FROM {system} WHERE type = 'theme' OR (type = 'module' AND status = 1) ORDER BY weight ASC, name ASC");
        foreach ($result as $record) {
          $record->info = unserialize($record->info);
          // Build a list of all enabled modules.
          if ($record->type == 'module') {
            $lists['module_enabled'][$record->name] = $record;
          }
          // Build a list of themes.
          if ($record->type == 'theme') {
            $lists['theme'][$record->name] = $record;
          }
          // Build a list of filenames so drupal_get_filename can use it.
          if ($record->status) {
            $lists['filepaths'][] = array(
              'type' => $record->type,
              'name' => $record->name,
              'filepath' => $record->filename,
            );
          }
        }
        foreach ($lists['theme'] as $key => $theme) {
          if (!empty($theme->info['base theme'])) {
            // Make a list of the theme's base themes.
            require_once dirname(__FILE__) . '/includes/theme.inc';
            $lists['theme'][$key]->base_themes = $this->drupalFindBaseThemes($lists['theme'], $key);
            // Don't proceed if there was a problem with the root base theme.
            if (!current($lists['theme'][$key]->base_themes)) {
              continue;
            }
            // Determine the root base theme.
            $base_key = key($lists['theme'][$key]->base_themes);
            // Add to the list of sub-themes for each of the theme's base themes.
            foreach (array_keys($lists['theme'][$key]->base_themes) as $base_theme) {
              $lists['theme'][$base_theme]->sub_themes[$key] = $lists['theme'][$key]->info['name'];
            }
            // Add the base theme's theme engine info.
            $lists['theme'][$key]->info['engine'] = isset($lists['theme'][$base_key]->info['engine']) ? $lists['theme'][$base_key]->info['engine'] : 'theme';
          }
          else {
            // A plain theme is its own engine.
            $base_key = $key;
            if (!isset($lists['theme'][$key]->info['engine'])) {
              $lists['theme'][$key]->info['engine'] = 'theme';
            }
          }
          // Set the theme engine prefix.
          $lists['theme'][$key]->prefix = $lists['theme'][$key]->info['engine'] == 'theme' ? $base_key : $lists['theme'][$key]->info['engine'];
        }
        cache_set('system_list', $lists, 'cache_bootstrap');
      }
      // To avoid a separate database lookup for the filepath, prime the
      // drupal_get_filename() static cache with all enabled modules and themes.
      foreach ($lists['filepaths'] as $item) {
        drupal_get_filename($item['type'], $item['name'], $item['filepath']);
      }
    }
    return $lists[$type];
  }

  /**
   * Backport of the DBTNG fetchAllAssoc() from Drupal 7.
   */
  private function dbFetchAllAssoc($query, $field) {
    $return = array();
    while ($result = db_fetch_object($query)) {
      if (isset($result->$field)) {
        $key = $result->$field;
        $return[$key] = $result;
      }
    }
    return $return;
  }

  /**
   * Backport of the DBTNG drupal_find_base_themes() from Drupal 7.
   */
  private function drupalFindBaseThemes($themes, $key, $used_keys = array()) {
    $base_key = $themes[$key]->info['base theme'];
    // Does the base theme exist?
    if (!isset($themes[$base_key])) {
      return array(
        $base_key => NULL,
      );
    }
    $current_base_theme = array(
      $base_key => $themes[$base_key]->info['name'],
    );
    // Is the base theme itself a child of another theme?
    if (isset($themes[$base_key]->info['base theme'])) {
      // Do we already know the base themes of this theme?
      if (isset($themes[$base_key]->base_themes)) {
        return $themes[$base_key]->base_themes + $current_base_theme;
      }
      // Prevent loops.
      if (!empty($used_keys[$base_key])) {
        return array(
          $base_key => NULL,
        );
      }
      $used_keys[$base_key] = TRUE;
      return $this->drupalFindBaseThemes($themes, $base_key, $used_keys) + $current_base_theme;
    }
    // If we get here, then this is our parent theme.
    return $current_base_theme;
  }

}
