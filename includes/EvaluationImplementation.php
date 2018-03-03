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
    $data = $keys = $viewsdata = $operations = array();
    // Fetching: file managed/Nodes/Users/Taxonomy terms.
    $keys = array(
      'file_managed' => array('file_managed', 'fid', 'f'),
      'nodes' => array('node', 'nid', 'n'),
      'users' => array('users', 'uid', 'u'),
      'terms' => array('taxonomy_term_data', 'tid', 't'),
    );
    $siteName = variable_get('site_name', 'Drupal');
    $data['info'] = array('site_name' => $siteName, 'base_url' => $base_url);
    foreach ($keys as $key => $val) {
      $param = array('t' => $val[0], 'a' => $val[2], 'f' => array($val[1]));
      $result = self::generateSql($param);
      $data[$key] = count($result);
    }
    // Fetching views data.
    if (module_exists('views')) {
      $param = array(
        't' => 'views_view',
        'a' => 'v',
        'f' => array('vid', 'name', 'description'),
      );
      $query = self::generateSql($param, TRUE);
      foreach ($query as $view) {
        $param = array(
          't' => 'views_display',
          'a' => 'v',
          'f' => array('id', 'display_title'),
          'c' => array(array('f' => 'vid', 'v' => $view->vid)),
        );
        $display_count = self::generateSql($param);
        array_push($viewsdata, array(
          'view' => $view->name,
          'description' => $view->description,
          'displays' => count($display_count),
        ));
      }
    }
    $data['viewsdata'] = $viewsdata;
    // Fetch Data of all enabled Modules.
    $param = array(
      't' => 'system',
      'a' => 's',
      'f' => array('filename', 'name', 'schema_version'),
      'c' => array(
        array('f' => 'status', 'v' => 1),
        array('f' => 'type', 'v' => 'module'),
      ),
    );
    $system = self::generateSql($param);
    foreach ($system as $module) {
      if ($module->name === 'upgrade_check' || $module->name === 'standard') {
        continue;
      }
      $operations[] = array(
        '_upgrade_check_modules_evaluation',
        array('module' => $module),
      );
    }
    // Fetch Data of all enabled Themes.
    $param = array(
      't' => 'system',
      'a' => 's',
      'f' => array('filename', 'name'),
      'c' => array(
        array('f' => 'status', 'v' => 1),
        array('f' => 'type', 'v' => 'theme'),
      ),
    );
    $themeSql = self::generateSql($param, TRUE);
    foreach ($themeSql as $theme) {
      $operations[] = array(
        '_upgrade_check_themes_evaluation',
        array('theme' => $theme),
      );
    }
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
    $theme_path = drupal_get_path('theme', variable_get('theme_default', NULL));
    $default_theme = substr($theme_path, strrpos($theme_path, "/") + 1);
    if ($theme->name == $default_theme) {
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
        $line = self::upgradeCheckCountLines($name);
        $themes['lines'] += $line;
        $themes['files'][$name] = $line;
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
        $filename = $filelocation . "/" . $module->name . ".module";
        if (empty($first)) {
          if (file_exists($filename)) {
            $modulefile = file_get_contents($filename);
          }
          else {
            $modulefile = "No Such File";
          }
          if (strpos($modulefile, 'include_once') !== FALSE &&
            strpos($modulefile, $module->name . '.features.inc') !== FALSE) {
            $modules['type'] = "Feature";
          }
        }
        $filename = $filelocation . "/" . $module->name . ".info";
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
          $line = self::upgradeCheckCountLines($name);
          $modules['lines'] += $line;
          $modules['files'][$name] = $line;
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
  private static function upgradeCheckCountLines($file) {
    $allC = $commentC = $codeC = $emptyC = $badEC = 0;
    $handle = fopen($file, "r");
    $regComment = '/^(\s*\/+\*+\*+)|(\s+\*+\s+)|(\s+\*+\/+)|(\s+\/+\/+)/';
    $regCode = '/^[\d\w\{\}\[\]\(\)\@\$\=\+\-\*\/\!\#\%\^\&\?\<\>\-\.\,\`\~\;\:\|\s\_]+/';
    while (!feof($handle)) {
      $content = fgets($handle);
      ++$allC;
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
    $result = $allC . '/' . $codeC  . '/' . $commentC . '/' . $emptyC;
    $result .= '/' . $badEC;
    return $result;
  }

  /**
   * Generate SQL.
   */
  private static function generateSql($data, $dontAll = FALSE) {
    $result = FALSE;
    if (!empty($data) && !empty($data['t']) && !empty($data['a'])) {
      $query = db_select($data['t'], $data['a']);
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

}
