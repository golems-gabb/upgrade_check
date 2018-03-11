<?php

namespace Upgrade_check;

require_once __DIR__ . '/EvaluationCode.php';

use Upgrade_check\EvaluationCode;

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
   * Implements _upgrade_check_json_form().
   */
  public static function upgradeCheckJsonForm() {
    $form = array();
    $text = 'Their is no json file to download.';
    $text .= 'Please Create one by clicking the below link';
    if (file_exists(variable_get('json_file_patch'))) {
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
    $data['site_info'] = array(
      'site_name' => variable_get('site_name', 'Drupal'),
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
    $themes = (new EvaluationCode)->themesEvaluation($theme);
    $context['results']['themes'][] = $themes;
    $context['sandbox']['progress']++;
  }

  /**
   * Implements _upgrade_check_modules_evaluation().
   */
  public static function upgradeCheckModulesEvaluation($module, &$context) {
    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
    }
    $modules = (new EvaluationCode)->modulesEvaluation($module);
    $context['results']['modules'][] = $modules;
    $context['sandbox']['progress']++;
  }

  /**
   * Fetching: Nodes/Files usage/Users/Image styles/Roles/Languages/Blocks.
   */
  private function upgradeCheckEntityData(&$data) {
    $keys = array(
      'nodes_count' => array('node', 'nid', 'n'),
      'existing_files_count' => array('file_usage', 'fid', 'f'),
      'users_count' => array('users', 'uid', 'u'),
      'image_styles_count' => array('image_styles', 'isid', 'i'),
      'roles_count' => array('users_roles', 'rid', 'u'),
      'languages_count' => array('languages', 'language', 'l'),
      'block_custom_count' => array('block_custom', 'bid', 'b'),
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
    $system = system_list('module_enabled');
    foreach ($system as $module) {
      $operations[] = array(
        '_upgrade_check_modules_evaluation',
        array('module' => (array) $module),
      );
    }
    return NULL;
  }

  /**
   * Fetch data of all enabled themes.
   */
  private function upgradeCheckThemesData(&$operations) {
    $themes = system_list('theme');
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
    $result = array();
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
    $file_name = $response['data']['site_info']['site_name'] . '.' . 'json';
    $file_path = file_unmanaged_save_data(json_encode($response), $file_name, FILE_EXISTS_REPLACE);
    variable_set('json_file_patch', $file_path);
    return FALSE;
  }

  /**
   * Implements _upgrade_check_json_form_submit().
   */
  public static function upgradeCheckJsonFormSubmit() {
    $siteName = variable_get('site_name', 'Drupal');
    $filePath = variable_get('json_file_patch');
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
    file_unmanaged_delete(variable_get('json_file_patch'));
    variable_del('json_file_patch');
    drupal_exit();
    return FALSE;
  }

}
