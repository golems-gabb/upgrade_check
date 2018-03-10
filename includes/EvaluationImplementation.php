<?php

namespace Upgrade_check;

class EvaluationImplementation {

  /**
   * Implements upgrade_check_form().
   */
  public static function upgradeCheckForm() {
    $form = array();
    $text = 'Welcome to upgrade check per Drupal 8.';
    $text .= 'Click on the link to get the upgrade score of your webresource.';
    $form['description'] = array(
      '#type' => 'markup',
      '#markup' => t('@text', array('@text' => $text)) . '<br>',
    );
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => 'Analyze',
    );
    return $form;
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
    $response = array();
    $data['modules'] = $context['results']['modules'];
    $data['themes'] = $context['results']['themes'];
    $response['data'] = $data;
    $file_name = $response['data']['info']['site_name'] . "." . "json";
    $file_path = file_unmanaged_save_data(json_encode($response), $file_name, FILE_EXISTS_REPLACE);
    variable_set("json_file_patch", $file_path);
    return FALSE;
  }

  /**
   * Implements _upgrade_check_json_form().
   */
  public static function upgradeCheckJsonForm() {
    $form = array();
    $text = 'Their is no json file to download.';
    $text .= 'Please Create one by clicking the below link';
    if (file_exists(variable_get("json_file_patch"))) {
      $options = array(
        'absolute' => TRUE,
        'html' => TRUE,
        'attributes' => array('target' => '_blank'),
      );
      $url = 'https:/gole.ms/evaluation/upgrade';
      $link = l('Upload Json', $url, $options);
      $markup = 'Please follow the steps to complete migration check';
      $markup .= ' process<br> ';
      $markup .= '1.Download Json File from the given below link.<br>';
      $markup .= '2.Upload the json file here -->';
      $markupText = $markup . ' ' . $link . '.<br>';
      $form['description'] = array(
        '#type' => 'markup',
        '#markup' => $markupText,
      );
      $form['submit'] = array(
        '#type' => 'submit',
        '#value' => 'Download JSON file',
      );
    }
    else {
      drupal_set_message(t('@text', array('@text' => $text)));
      drupal_goto(UPGRADE_CHECK_EVALUATION);
    }
    return $form;
  }

  /**
   * Implements upgrade_check_form_submit().
   */
  public static function upgradeCheckFormSubmit() {
    global $base_url;
    $data = $operations = array();
    $evIm = new EvaluationImplementation;
    $siteName = variable_get('site_name', 'Drupal');
    $data['info'] = array('site_name' => $siteName, 'base_url' => $base_url);
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
   * Implements _upgrade_check_json_form_submit().
   */
  public static function upgradeCheckJsonFormSubmit() {
    $siteName = variable_get('site_name', 'Drupal');
    $filePath = variable_get("json_file_patch");
    $file = fopen($filePath, 'r') or die("Please give suitable Permission to files folder");
    $fileSize = filesize($filePath);
    header("Content-type: application/json");
    header("Content-Type: application/force-download");
    header("Content-Type: application/download");
    header("Content-Description: File Transfer");
    header("Content-Disposition: attachment;filename=" . $siteName . "." . "json");
    header("Content-length: $fileSize");
    header("Cache-control: private"); //use this to open files directly
    while (!feof($file)) {
      $buffer = fread($file, 2048);
      echo $buffer;
      flush();
    }
    fclose($file);
    file_unmanaged_delete(variable_get("json_file_patch"));
    variable_del("json_file_patch");
    drupal_exit();
    return FALSE;
  }

  /**
   * Implements _upgrade_check_themes_evaluation().
   */
  public static function upgradeCheckThemesEvaluation($theme, &$context) {
    $themes = array();
    $themes['lines'] = 0;
    $themes['files'] = NULL;
    $themes['type'] = "Enabled";
    $themeName = !empty($theme->name) ? $theme->name : '';
    $theme_path = drupal_get_path('theme', variable_get('theme_default', NULL));
    $default_theme = substr($theme_path, strrpos($theme_path, "/") + 1);
    if ($themeName == $default_theme) {
      $themes['type'] = "Default";
    }
    $filelocation = substr($theme->filename, 0, strripos($theme->filename, '/'));
    $directory = new \RecursivedirectoryIterator($filelocation);
    $iterator = new \RecursiveIteratorIterator($directory);
    $ident = array('.info', '.txt', '/.', '/..', '.png', '.gif', '.jpeg');
    foreach ($iterator as $name => $object) {
      $status = FALSE;
      foreach ($ident as $val) {
        if (strpos($name, $val) !== FALSE) {
          $status = TRUE;
        }
      }
      if (!empty($status)) {
        continue;
      }
      else {
        $data = self::upgradeCheckCountLines($name, $themeName);
        $themes['lines'] += $data['all_strings'];
        $themes['files'][$name] = $data;
      }
    }
    $themes['name'] = $theme->name;
    $context['results']['themes'][] = $themes;
  }

  /**
   * Implements _upgrade_check_modules_evaluation().
   */
  public static function upgradeCheckModulesEvaluation($module, &$context) {
    $modules = $modules['files'] = array();
    $modules['type'] = "Custom";
    $modules['lines'] = 0;
    $moduleName = !empty($module->name) ? $module->name : '';
    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
      $first = $second = 0;
    }
    $modules['version'] = $module->schema_version;
    $filelocation = substr($module->filename, 0, strripos($module->filename, '/'));
    if (substr($filelocation, '0', '1') == 'm') {
      $modules['type'] = "Core";
    }
    else {
      $directory = new \RecursivedirectoryIterator($filelocation);
      $iterator = new \RecursiveIteratorIterator($directory);
      foreach ($iterator as $name => $object) {
        $status = FALSE;
        $filename = $filelocation . "/" . $moduleName . ".module";
        if (empty($first)) {
          if (file_exists($filename)) {
            $modulefile = file_get_contents($filename);
          }
          else {
            $modulefile = "No Such File";
          }
          if (strpos($modulefile, 'include_once') !== FALSE &&
            strpos($modulefile, $moduleName . '.features.inc') !== FALSE) {
            $modules['type'] = "Feature";
          }
        }
        $filename = $filelocation . "/" . $moduleName . ".info";
        if (empty($second)) {
          if (file_exists($filename)) {
            $modulefile = file_get_contents($filename);
          }
          else {
            $modulefile = "No Such File";
          }
          if (strpos($modulefile, 'Information added by Drupal.org') !== FALSE || strpos($modulefile, 'datestamp') !== FALSE) {
            $modules['type'] = "Contrib";
          }
          $second = 1;
        }
        $ident = array('.info', '.txt', '/.', '/..', '.png', '.gif', '.jpeg');
        foreach ($ident as $val) {
          if (strpos($name, $val) !== FALSE) {
            $status = TRUE;
          }
        }
        if (!empty($status)) {
          continue;
        }
        else {
          $data = self::upgradeCheckCountLines($name, $moduleName);
          $modules['lines'] += $data['all_strings'];
          $modules['files'][$name] = $data;
        }
      }
    }
    $modules['name'] = $module->name;
    $context['results']['modules'][] = $modules;
    $context['sandbox']['progress']++;
  }

  /**
   * Calculates file`s number of lines.
   */
  private static function upgradeCheckCountLines($file, $name) {
    $allC = $commentC = $codeC = $emptyC = $badEC = 0;
    $functions = $result = array();
    $handle = fopen($file, "r");
    $regComment = '/^(\s*\/+\*+\*+)|(\s+\*+\s+)|(\s+\*+\/+)|(\s+\/+\/+)/';
    $regFunction = '/function\s*(_*)(' . $name . '_)*(\w+)\s*\(/';
    $regClass = '/class\s*(\w+)\s*(\w+\s\w+)*\s\{/';
    $regInterface = '/interface\s*(\w+)\s*(\w+\s\w+)*\s\{/';
    while (!feof($handle)) {
      $content = fgets($handle);
      ++$allC;
      if (preg_match($regFunction, $content, $function)) {
        if (!empty($function)) {
          if (!empty($function[1]) && !empty($function[2]) && !empty($function[3])) {
            $functions['custom_function'][] = $function[3];
          }
          elseif (empty($function[1]) && !empty($function[2]) && !empty($function[3])) {
            $functions['function'][] = $function[3];
          }
          elseif (empty($function[1]) && empty($function[2]) && !empty($function[3])) {
            $functions['object'][] = $function[3];
          }
        }
      }
      elseif (preg_match($regClass, $content, $class)) {
        if (!empty($class) && !empty($class[1])) {
          $className = $class[1];
          $className .= !empty($class[2]) ? ' ' . $class[2] : '';
          $functions['class'][] = $className;
        }
      }
      elseif (preg_match($regClass, $content, $class)) {
        if (!empty($class) && !empty($class[1])) {
          $className = $class[1];
          $className .= !empty($class[2]) ? ' ' . $class[2] : '';
          $functions['class'][] = $className;
        }
      }
      elseif (preg_match($regInterface, $content, $interface)) {
        if (!empty($interface) && !empty($interface[1])) {
          $interfaceName = $interface[1];
          $interfaceName .= !empty($interface[2]) ? ' ' . $interface[2] : '';
          $functions['interface'][] = $interfaceName;
        }
      }
      if ($content === "\n" || empty($content)) {
        ++$emptyC;
      }
      elseif ($content === "\r" || $content === "\r\n") {
        ++$badEC;
      }
      elseif (preg_match($regComment, $content)) {
        ++$commentC;
      }
      else {
        ++$codeC;
      }
    }
    fclose($handle);
    $result['all_strings'] = $allC;
    $result['code_strings'] = $codeC;
    $result['comment_strings'] = $commentC;
    $result['empty_strings'] = $emptyC;
    $result['bad_strings'] = $badEC;
    $result['logic'] = $functions;
    return $result;
  }

  /**
   * Fetching: Nodes/Files usage/Users/Image styles/Roles/Languages/Blocks.
   */
  private function upgradeCheckEntityData(&$data) {
    $keys = array(
      'nodes' => array('node', 'nid', 'n'),
      'file_managed' => array('file_usage', 'fid', 'f'),
      'users' => array('users', 'uid', 'u'),
      'image_styles' => array('image_styles', 'isid', 'i'),
      'roles' => array('users_roles', 'rid', 'u'),
      'languages' => array('languages', 'language', 'l'),
      'block_custom' => array('block_custom', 'bid', 'b'),
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
      if (empty($result[$link->menu_name])) {
        $result[$link->menu_name] = 1;
      }
      else {
        ++$result[$link->menu_name];
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
      $result[$node->type] = $node->module;
    }
    return $result;
  }

  /**
   * Fetch fields data.
   */
  private function upgradeCheckFieldsData() {
    $result = array();
    $param = array(
      't' => 'field_config_instance',
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
          if (empty($result[$taxonomyVocabulary[$value]])) {
            $result[$taxonomyVocabulary[$value]] = 1;
          }
          else {
            ++$result[$taxonomyVocabulary[$value]];
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
      't' => 'taxonomy_vocabulary',
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
      't' => 'taxonomy_term_data',
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
    $param = array(
      't' => 'system',
      'a' => 's',
      'f' => array('filename', 'name', 'schema_version'),
      'c' => array(
        array('f' => 'status', 'v' => 1),
        array('f' => 'type', 'v' => 'module'),
      ),
    );
    $system = $this->generateSql($param);
    foreach ($system as $module) {
      if ($module->name === 'upgrade_check' || $module->name === 'standard') {
        continue;
      }
      $operations[] = array(
        '_upgrade_check_modules_evaluation',
        array('module' => $module),
      );
    }
    return NULL;
  }

  /**
   * Fetch data of all enabled themes.
   */
  private function upgradeCheckThemesData(&$operations) {
    $param = array(
      't' => 'system',
      'a' => 's',
      'f' => array('filename', 'name'),
      'c' => array(
        array('f' => 'status', 'v' => 1),
        array('f' => 'type', 'v' => 'theme'),
      ),
    );
    $themeSql = $this->generateSql($param, TRUE);
    foreach ($themeSql as $theme) {
      $operations[] = array(
        '_upgrade_check_themes_evaluation',
        array('theme' => $theme),
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
          'view' => $view->name,
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
    $result = FALSE;
    if (!empty($data) && !empty($data['t']) && !empty($data['a'])) {
      $query = db_select($data['t'], $data['a']);
      if (!empty($data['j']) && !empty($data['j']['t'])) {
        if (!empty($data['j']['a']) && !empty($data['j']['jt']) && !empty($data['j']['con'])) {
          $sqlVal = $data['a'] . '.' . $data['j']['con']['left'] . ' = ';
          $sqlVal .= $data['j']['a'] . '.' . $data['j']['con']['right'];
          if ($data['j']['jt'] === 'inner') {
            $query->innerJoin($data['j']['t'], $data['j']['a'], $sqlVal);
          }
          elseif ($data['j']['jt'] === 'left') {
            $query->leftJoin($data['j']['t'], $data['j']['a'], $sqlVal);
          }
          elseif ($data['j']['jt'] === 'right') {
            $query->rightJoin($data['j']['t'], $data['j']['a'], $sqlVal);
          }
        }
        if (!empty($data['j']['f'])) {
          $query->fields($data['j']['a'], $data['j']['f']);
        }
        if (!empty($data['j']['c']) && is_array($data['j']['c'])) {
          foreach ($data['j']['c'] as $value) {
            if (!empty($value) && !empty($value['f']) && !empty($value['v'])) {
              if (!empty($value['p'])) {
                $query->condition($value['f'], $value['v'], $value['p']);
              }
              else {
                $query->condition($value['f'], $value['v']);
              }
            }
          }
        }
      }
      if (!empty($data['f']) && is_array($data['f'])) {
        $query->fields($data['a'], $data['f']);
      }
      if (!empty($data['c']) && is_array($data['c'])) {
        foreach ($data['c'] as $value) {
          if (!empty($value) && !empty($value['f']) && !empty($value['v'])) {
            if (!empty($value['p'])) {
              $query->condition($value['f'], $value['v'], $value['p']);
            }
            else {
              $query->condition($value['f'], $value['v']);
            }
          }
        }
      }
      if (!empty($dontAll)) {
        $result = $query->execute();
      }
      else {
        $result = $query->execute()->fetchAll();
      }
    }
    return $result;
  }

  /**
   * Processes a task to fetch available update data for a single project.
   *
   * Once the release history XML data is downloaded, it is parsed and saved
   * into the {cache_update} table in an entry just for that project.
   *
   * @param $project
   *   Associative array of information about the project to fetch data for.
   *
   * @return
   *   TRUE if we fetched parsable XML, otherwise FALSE.
   */
  /*function _update_process_fetch_task($project) {
    global $base_url;
    $fail = &drupal_static(__FUNCTION__, array());
    // This can be in the middle of a long-running batch, so REQUEST_TIME won't
    // necessarily be valid.
    $now = time();
    if (empty($fail)) {
      // If we have valid data about release history XML servers that we have
      // failed to fetch from on previous attempts, load that from the cache.
      if (($cache = _update_cache_get('fetch_failures')) && ($cache->expire > $now)) {
        $fail = $cache->data;
      }
    }

    $max_fetch_attempts = variable_get('update_max_fetch_attempts', UPDATE_MAX_FETCH_ATTEMPTS);

    $success = FALSE;
    $available = array();
    $site_key = drupal_hmac_base64($base_url, drupal_get_private_key());
    $url = _update_build_fetch_url($project, $site_key);
    $fetch_url_base = _update_get_fetch_url_base($project);
    $project_name = $project['name'];

    if (empty($fail[$fetch_url_base]) || $fail[$fetch_url_base] < $max_fetch_attempts) {
      $xml = drupal_http_request($url);
      if (!isset($xml->error) && isset($xml->data)) {
        $data = $xml->data;
      }
    }

    if (!empty($data)) {
      $available = update_parse_xml($data);
      // @todo: Purge release data we don't need (http://drupal.org/node/238950).
      if (!empty($available)) {
        // Only if we fetched and parsed something sane do we return success.
        $success = TRUE;
      }
    }
    else {
      $available['project_status'] = 'not-fetched';
      if (empty($fail[$fetch_url_base])) {
        $fail[$fetch_url_base] = 1;
      }
      else {
        $fail[$fetch_url_base]++;
      }
    }

    $frequency = variable_get('update_check_frequency', 1);
    $cid = 'available_releases::' . $project_name;
    _update_cache_set($cid, $available, $now + (60 * 60 * 24 * $frequency));

    // Stash the $fail data back in the DB for the next 5 minutes.
    _update_cache_set('fetch_failures', $fail, $now + (60 * 5));

    // Whether this worked or not, we did just (try to) check for updates.
    variable_set('update_last_check', $now);

    // Now that we processed the fetch task for this project, clear out the
    // record in {cache_update} for this task so we're willing to fetch again.
    _update_cache_clear('fetch_task::' . $project_name);

    return $success;
  }*/

  /**
   * Clears out all the cached available update data and initiates re-fetching.
   */
  /*function _update_refresh() {
    module_load_include('inc', 'update', 'update.compare');

    // Since we're fetching new available update data, we want to clear
    // our cache of both the projects we care about, and the current update
    // status of the site. We do *not* want to clear the cache of available
    // releases just yet, since that data (even if it's stale) can be useful
    // during update_get_projects(); for example, to modules that implement
    // hook_system_info_alter() such as cvs_deploy.
    _update_cache_clear('update_project_projects');
    _update_cache_clear('update_project_data');

    $projects = update_get_projects();

    // Now that we have the list of projects, we should also clear our cache of
    // available release data, since even if we fail to fetch new data, we need
    // to clear out the stale data at this point.
    _update_cache_clear('available_releases::', TRUE);

    foreach ($projects as $key => $project) {
      update_create_fetch_task($project);
    }
  }*/

  //if (function_exists('drupal_get_path')) {

}
