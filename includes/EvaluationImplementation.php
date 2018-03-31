<?php

namespace Upgrade_check;

require_once __DIR__ . '/EvaluationCode.php';

use Upgrade_check\EvaluationCode;

class EvaluationImplementation {

  private $moduleName = 'upgrade_check';

  private $regType = '/\.(\w+)$/';

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
      '#markup' => t('!text', array('!text' => $text)),
    );
    $form['analyze'][UPGRADE_CHECK_DATA_METHOD] = array(
      '#type' => 'radios',
      '#title' => t('Data transfer method'),
      '#description' => t('Depending on the method selected, the method for data transfer for analysis will be changed.'),
      '#options' => array(
        'manual' => t('Manual'),
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
    if (file_exists(variable_get(UPGRADE_CHECK_JSON_PATH))) {
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
        '#markup' => t('Please follow the steps to complete migration check process:'),
      );
      $form['download']['description_list_one'] = array(
        '#type' => 'item',
        '#markup' => t('I - Download Json File from the given below link.'),
      );
      $form['download']['description_list_two'] = array(
        '#type' => 'item',
        '#markup' => t('II - Upload the json file here --> !link',
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
    if (module_exists('file')) {
      $data['existing_files_count'] = $evIm->upgradeCheckFilesData();
    }
    $data['nodes_data'] = $evIm->upgradeCheckNodesData();
    $data['menu_data'] = $evIm->upgradeCheckMenusData();
    if (module_exists('taxonomy')) {
      $data['taxonomy_data'] = $evIm->upgradeCheckTaxonomyData();
    }
    if (module_exists('views')) {
      $data['views_data'] = $evIm->upgradeCheckViewsData();
    }
    if (module_exists('comment')) {
      $data['comments'] = $evIm->upgradeCheckCommentData();
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
   * Implements _upgrade_check_themes_evaluation().
   */
  public static function upgradeCheckThemesEvaluation($theme, &$context) {
    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
    }
    $themes = (new EvaluationCode)->themesEvaluation($theme);
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
    $modules = (new EvaluationCode)->modulesEvaluation($module);
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
      'users_count' => array('users', 'uid', 'u'),
      'image_styles_count' => array('image_styles', 'isid', 'i'),
      'roles_count' => array('users_roles', 'rid', 'u'),
    );
    if (module_exists('locale')) {
      $keys['languages_count'] = array('languages', 'language', 'l');
    }
    if (module_exists('block')) {
      $keys['block_custom_count'] = array('block_custom', 'bid', 'b');
    }
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
    return $this->upgradeCheckCreateAssociatedArray($result, 'link_counts');
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
      $result[] = array(
        'name' => $this->generateCryptName($node->type),
        'module' => $node->module,
      );
    }
    return $result;
  }

  /**
   * Fetch comment data.
   */
  private function upgradeCheckCommentData() {
    $result = array();
    $param = array(
      't' => 'comment',
      'a' => 'c',
      'f' => array('cid', 'pid'),
    );
    $comments = $this->generateSql($param);
    foreach ($comments as $comment) {
      $key = !empty($comment->pid) ? 'children_comments' : 'parent_comments';
      if (empty($result[$key])) {
        $result[$key] = 1;
      }
      else {
        ++$result[$key];
      }
    }
    return $result;
  }

  /**
   * Fetch fields data.
   */
  private function upgradeCheckFieldsData() {
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
   * Fetch files data.
   */
  private function upgradeCheckFilesData() {
    $param = array(
      't' => 'file_managed',
      'a' => 'f',
      'f' => array('filesize', 'uri'),
    );
    $sql = $this->generateSql($param);
    if (!empty($sql)) {
      foreach ($sql as $key => $value) {
        $result[$key]['filesize'] = 0;
        $result[$key]['type'] = 'undefined';
        if (!empty($value) && !empty($value->filesize)) {
          $result[$key]['filesize'] = $value->filesize;
        }
        if (!empty($value) && !empty($value->uri)) {
          preg_match($this->regType, $value->uri, $type);
          if (!empty($type) && !empty($type[1])) {
            $result[$key]['type'] = $type[1];
          }
        }
      }
    }
    return !empty($result) ? $result : array();
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
    return $this->upgradeCheckCreateAssociatedArray($result, 'term_counts');
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
    $system = EvaluationCode::upgradeCheckSubmodules(system_list('module_enabled'));
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
    $data_array = array(
      'filters',
      'sorts',
      'fields',
      'displays',
      'relationships',
      'arguments',
    );
    if (module_exists('views')) {
      $param = array(
        't' => 'views_view',
        'a' => 'v',
        'f' => array('vid', 'name', 'description'),
      );
      $query = $this->generateSql($param, TRUE);
      foreach ($query as $key => $view) {
        $param = array(
          't' => 'views_display',
          'a' => 'v',
          'f' => array(
            'id',
            'display_title',
            'display_options',
            'display_plugin',
          ),
          'c' => array(array('f' => 'vid', 'v' => $view->vid)),
        );
        $display_count = $this->generateSql($param);
        $viewsdata[$key]['view'] = $this->generateCryptName($view->name);
        $viewsdata[$key]['description'] = $this->generateCryptName($view->description);
        $viewsdata[$key]['count_displays'] = count($display_count);
        foreach ($display_count as $key_d => $value) {
          $viewsdata[$key]['displays'][$key_d]['style_plugin'] = '';
          if (!empty($value) && !empty($value->display_options)) {
            $data = unserialize($value->display_options);
            $viewsdata[$key]['displays'][$key_d]['exposed_block'] = FALSE;
            $viewsdata[$key]['displays'][$key_d]['cache'] = FALSE;
            if (!empty($value->display_plugin)) {
              $viewsdata[$key]['displays'][$key_d]['display_plugin'] = $value->display_plugin;
            }
            if (!empty($data)) {
              foreach ($data as $name => $val) {
                if (!empty($val) && in_array($name, $data_array, TRUE)) {
                  $count_val = $name === 'displays' ? count(array_diff($val, array(0))) : count($val);
                  $viewsdata[$key]['displays'][$key_d][$name] = $count_val;
                }
                elseif ($name === 'style_plugin' && !empty($val)) {
                  $viewsdata[$key]['displays'][$key_d][$name] = $val;
                }
                elseif ($name === 'title' && !empty($val)) {
                  $viewsdata[$key]['displays'][$key_d][$name] = $this->generateCryptName($val);
                }
                elseif (($name === 'cache' && !empty($val['type']) && $val['type'] !== 'none')
                  || ($name === 'exposed_block' && !empty($val))) {
                  $viewsdata[$key]['displays'][$key_d][$name] = TRUE;
                }
              }
            }
          }
        }
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
    $eC = new EvaluationCode;
    $response = array();
    $data['modules'] = $eC->upgradeCheckSubmodulesDeleteInfo($context['results']['modules']);
    $data['themes'] = $context['results']['themes'];
    $response['data'] = $data;
    $file_name = $response['data']['site_info']['site_name'] . '.' . 'json';
    $file_path = file_unmanaged_save_data(drupal_json_encode($response), 'public://' . $file_name, FILE_EXISTS_REPLACE);
    variable_set(UPGRADE_CHECK_JSON_PATH, $file_path);
    return FALSE;
  }

  /**
   * Implements _upgrade_check_json_form_submit().
   */
  public static function upgradeCheckJsonFormSubmit() {
    $siteName = variable_get('site_name', 'Drupal');
    $filePath = variable_get(UPGRADE_CHECK_JSON_PATH);
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
    file_unmanaged_delete(variable_get(UPGRADE_CHECK_JSON_PATH));
    variable_del(UPGRADE_CHECK_JSON_PATH);
    drupal_exit();
    return FALSE;
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
   * Convert to associate array.
   */
  private static function upgradeCheckCreateAssociatedArray($datas, $key) {
    if (!empty($datas)) {
      foreach ($datas as $name => $data) {
        if (!empty($data) && !empty($name)) {
          $datas[] = array('name' => $name, $key => $data);
          unset($datas[$name]);
        }
      }
    }
    return $datas;
  }

}
