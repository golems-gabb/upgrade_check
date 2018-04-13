<?php

namespace Upgrade_check;

require_once __DIR__ . '/EvaluationCode.php';

use Upgrade_check\EvaluationCode;

class EvaluationImplementation {

  private $moduleName = 'upgrade_check';

  private $regType = '/\.(\w+)$/';

  const METHOD = 'aes128';

  const KEY = 'k%B3#W@j8ddFTsd*F&V2x';

  const IV = '@97#3ERDfg9&3e4f';

  const UPGRADE_CHECK_ACCESS_NAME = 'upgrade_check_access_name';

  const UPGRADE_CHECK_RESULT_FORM = 'upgrade_check_result_form';

  const UPGRADE_CHECK_METATAG_NAME = 'upgrade_check_tag';

  const UPGRADE_CHECK_SALT_FIELD_NAME = 'upgrade_check_test_enity_name';

  const UPGRADE_CHECK_JSON_PATH = 'upgrade_check_json_file_path';

  const UPGRADE_CHECK_PREFIX = 'upgrade_check_';

  const UPGRADE_CHECK_DATA_METHOD = 'data_transfer_method';

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
    $method = variable_get(self::UPGRADE_CHECK_PREFIX . self::UPGRADE_CHECK_DATA_METHOD, 'manual');
    $form['analyze'][self::UPGRADE_CHECK_DATA_METHOD] = array(
      '#type' => 'radios',
      '#title' => t('Data transfer method'),
      '#description' => t('Depending on the method selected, the method for data transfer for analysis will be changed.'),
      '#options' => array(
        'manual' => t('Manual'),
        'automatic' => t('Automatic'),
      ),
      '#disabled' => 'disabled',
      '#default_value' => !empty($method) ? $method : variable_get(self::UPGRADE_CHECK_DATA_METHOD, 'manual'),
    );
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Analyze'),
    );
    return $form;
  }

  /**
   * Implements _upgrade_check_json_form().
   */
  public static function upgradeCheckJsonForm() {
    $form = array();
    if (file_exists(variable_get(self::UPGRADE_CHECK_JSON_PATH, NULL))) {
      $method = variable_get(self::UPGRADE_CHECK_PREFIX . self::UPGRADE_CHECK_DATA_METHOD, 'manual');
      if (!empty($method) && $method !== 'automatic') {
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
        $textButton = 'Download JSON';
      }
      else {
        $textButton = 'Transfer data';
        $form['download'] = array(
          '#type' => 'fieldset',
          '#title' => t('Transfer JSON data'),
        );
        $form['download']['description'] = array(
          '#type' => 'item',
          '#value' => t('Please click to button "Transfer data" that complete migration check process. Data will be sent automatically.'),
        );
      }
      $form['submit'] = array(
        '#type' => 'submit',
        '#value' => t($textButton),
      );
    }
    else {
      $text = 'Their is no json file to download. ';
      $text .= 'Please create one by clicking the below link!';
      drupal_set_message(t($text));
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
    $hash = variable_get(self::UPGRADE_CHECK_SALT_FIELD_NAME, md5(uniqid(rand())));
    $form[UPGRADE_CHECK_SETTINGS_FORM][self::UPGRADE_CHECK_SALT_FIELD_NAME] = array(
      '#type' => 'textfield',
      '#title' => t('Default salt'),
      '#description' => t('Automatic salt generation to encrypt real entity names.'),
      '#disabled' => 'disabled',
      '#default_value' => $hash,
      '#value' => $hash,
    );
    return system_settings_form($form);
  }

  /**
   * Implements _upgrade_check_result_form().
   */
  public static function upgradeCheckResultForm() {
    $data = self::upgradeCheckCryptUserData(NULL, $param = 'decrypt');
    if (!empty($data)) {
      $form[self::UPGRADE_CHECK_RESULT_FORM] = array(
        '#type' => 'fieldset',
        '#title' => t('User settings'),
      );
      $form[self::UPGRADE_CHECK_RESULT_FORM]['description'] = array(
        '#type' => 'item',
        '#value' => t('Use this data to authorize here - !link',
          array(
            '!link' => l(UPGRADE_CHECK_URL, UPGRADE_CHECK_URL),
          )
        ),
      );
      $form[self::UPGRADE_CHECK_RESULT_FORM]['name'] = array(
        '#type' => 'textfield',
        '#title' => t('Username'),
        '#description' => t('Username.'),
        '#disabled' => 'disabled',
        '#default_value' => $data['name'],
      );
      $form[self::UPGRADE_CHECK_RESULT_FORM]['pass'] = array(
        '#type' => 'textfield',
        '#title' => t('Password'),
        '#description' => t('Password.'),
        '#disabled' => 'disabled',
        '#default_value' => $data['pass'],
      );
    }
    else {
      $form[self::UPGRADE_CHECK_RESULT_FORM] = array(
        '#type' => 'fieldset',
        '#title' => t('Missing data for authorization.'),
      );
    }
    return $form;
  }

  /**
   * Implements upgrade_check_form_submit().
   */
  public static function upgradeCheckFormSubmit($form_state) {
    global $base_url;
    $data = $operations = array();
    if (!empty($form_state['values']['data_transfer_method'])) {
      variable_set(self::UPGRADE_CHECK_PREFIX . self::UPGRADE_CHECK_DATA_METHOD, $form_state['values']['data_transfer_method']);
    }
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
    if (module_exists('filefield')) {
      $data['existing_files_count'] = $evIm->upgradeCheckFilesData();
    }
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
      'users_count' => array('users', 'uid', 'u'),
      'roles_count' => array('role', 'rid', 'u'),
    );
    if (module_exists('block')) {
      $keys['block_custom_count'] = array(
        'blocks',
        'bid',
        'b',
        array(
          array('f' => 'module', 'v' => 'block'),
        ),
      );
    }
    if (module_exists('locale')) {
      $keys['languages_count'] = array('languages', 'language', 'l');
    }
    foreach ($keys as $key => $val) {
      $param = array('t' => $val[0], 'a' => $val[2], 'f' => array($val[1]));
      if (!empty($val[3])) {
        $param['c'] = $val[3];
      }
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
   * Fetch fields data.
   */
  private function upgradeCheckFieldsData() {
    $param = array(
      't' => 'content_node_field',
      'a' => 'fci',
      'f' => array('active', 'module', 'type'),
      'j' => array(
        't' => 'content_node_field_instance',
        'a' => 'fc',
        'f' => array('type_name'),
        'con' => array('left' => 'field_name', 'right' => 'field_name'),
        'jt' => 'left',
      ),
    );
    $result = $this->generateSql($param);
    if (!empty($result)) {
      foreach ($result as $key => $value) {
        if (!empty($value) && !empty($value->type_name)) {
          $result[$key]->bundle = $this->generateCryptName($value->type_name);
          unset($result[$key]->type_name);
        }
        $result[$key]->entity_type = 'node';
      }
    }
    return $result;
  }

  /**
   * Fetch files data.
   */
  private function upgradeCheckFilesData() {
    $param = array(
      't' => 'files',
      'a' => 'f',
      'f' => array('filesize', 'filepath'),
    );
    $sql = $this->generateSql($param);
    if (!empty($sql)) {
      foreach ($sql as $key => $value) {
        $result[$key]['filesize'] = 0;
        $result[$key]['type'] = 'undefined';
        if (!empty($value) && !empty($value->filesize)) {
          $result[$key]['filesize'] = $value->filesize;
        }
        if (!empty($value) && !empty($value->filepath)) {
          preg_match($this->regType, $value->filepath, $type);
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
      't' => 'vocabulary',
      'a' => 't',
      'f' => array('vid', 'name'),
    );
    $vocabularies = $this->generateSql($param);
    foreach ($vocabularies as $vocabulary) {
      $result[$vocabulary->vid] = $vocabulary->name;
    }
    return $result;
  }

  /**
   * Fetch taxonomy terms data.
   */
  private function upgradeCheckTaxonomyTermsData() {
    $result = array();
    $param = array(
      't' => 'term_data',
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
    $system = $this->systemList('theme');
    foreach ($system as $theme) {
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
    $key = 1;
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
      while ($view = db_fetch_object($query)) {
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
        $key++;
      }
    }
    return $viewsdata;
  }

  /**
   * Fetch comment data.
   */
  private function upgradeCheckCommentData() {
    $result = array();
    $param = array(
      't' => 'comments',
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
          foreach ($data['j']['f'] as $valFC) {
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
          return db_query($query);
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
    $data['themes'] = $context['results']['themes'];
    $response['data'] = $data;
    $file_name = $response['data']['site_info']['site_name'] . '.' . 'json';
    $file_path = file_save_data(json_encode($response), $file_name, FILE_EXISTS_REPLACE);
    variable_set(self::UPGRADE_CHECK_JSON_PATH, $file_path);
    return FALSE;
  }

  /**
   * Implements _upgrade_check_json_form_submit().
   */
  public static function upgradeCheckJsonFormSubmit() {
    $method = variable_get(self::UPGRADE_CHECK_PREFIX . self::UPGRADE_CHECK_DATA_METHOD, 'manual');
    if (!empty($method) && $method === 'automatic') {
      return self::upgradeCheckJsonFormSubmitAutomatic();
    }
    return self::upgradeCheckJsonFormSubmitManualy();
  }

  /**
   * Implements upgradeCheckJsonFormSubmitAutomatic().
   */
  public static function upgradeCheckJsonFormSubmitAutomatic() {
    global $base_url;
    $filePath = variable_get(self::UPGRADE_CHECK_JSON_PATH, NULL);
    $file = file_get_contents($filePath);
    if (!empty($file)) {
      global $user;
      $password = user_password(15);
      if (!empty($user->mail) && !empty($password)) {
        $name = self::UPGRADE_CHECK_PREFIX . self::UPGRADE_CHECK_METATAG_NAME;
        $meta = variable_get($name, NULL);
        if (empty($meta)) {
          $meta = self::generateCryptMetatag();
          variable_set($name, $meta);
        }
        preg_match('/^\w+/', $user->mail, $name);
        $data = array(
          'name' => $name[0],
          'mail' => $user->mail,
          'pass' => $password,
          'url' => $base_url,
          'metatag' => $meta,
          'data' => $file,
        );
        $result = self::upgradeCheckCurl($data);
        if (!empty($result)) {
          $string = array('name' => $name[0], 'pass' => $password);
          $result = self::upgradeCheckCryptUserData($string);
          if (!empty($result)) {
            file_delete(variable_get(self::UPGRADE_CHECK_JSON_PATH, NULL));
            variable_del(self::UPGRADE_CHECK_JSON_PATH);
            drupal_set_message(t('The data is successfully sent.'));
            drupal_goto(UPGRADE_CHECK_RESULT);
          }
        }
      }
    }
  }

  /**
   * Implements upgradeCheckJsonFormSubmitManualy().
   */
  public static function upgradeCheckJsonFormSubmitManualy() {
    $siteName = variable_get('site_name', 'Drupal');
    $filePath = variable_get(self::UPGRADE_CHECK_JSON_PATH, NULL);
    $file = fopen($filePath, 'r') or die('Please give suitable Permission to files folder');
    $fileSize = filesize($filePath);
    header('Content-type: application/json');
    header('Content-Type: application/force-download');
    header('Content-Type: application/download');
    header('Content-Description: File Transfer');
    header('Content-Disposition: attachment;filename=' . $siteName . '.' . 'json');
    header('Content-length: ' . $fileSize);
    header('Cache-control: private');
    while (!feof($file)) {
      $buffer = fread($file, 2048);
      echo $buffer;
      flush();
    }
    fclose($file);
    file_delete(variable_get(self::UPGRADE_CHECK_JSON_PATH, NULL));
    variable_del(self::UPGRADE_CHECK_JSON_PATH);
    exit();
  }

  /**
   * Implements upgradeCheckAddMetatag().
   */
  public static function upgradeCheckAddMetatag(&$vars) {
    $method = variable_get(self::UPGRADE_CHECK_PREFIX . self::UPGRADE_CHECK_DATA_METHOD, 'manual');
    if (!empty($method) && $method === 'automatic') {
      $name = self::UPGRADE_CHECK_PREFIX . self::UPGRADE_CHECK_METATAG_NAME;
      $data = variable_get($name, NULL);
      if (empty($data)) {
        $data = self::generateCryptMetatag();
        variable_set($name, $data);
      }
      $name = str_replace('_', '-', self::UPGRADE_CHECK_METATAG_NAME);
      $meta = '<meta name="' . $name . '" content="' . $data . '">';
      $vars['head'] = drupal_set_html_head($meta);
    }
    return FALSE;
  }

  /**
   * Implements upgradeCheckCryptUserData().
   */
  public static function upgradeCheckCryptUserData($data = NULL, $param = 'encrypt') {
    $key = base64_encode(self::KEY);
    $iv = self::IV;
    if (!empty($data) && $param === 'encrypt') {
      $string = serialize($data);
      if (function_exists('openssl_encrypt')) {
        $dataUser = openssl_encrypt($string, self::METHOD, $key, 0, $iv);
      }
      else {
        $dataUser = base64_encode($string);
      }
      if (!empty($dataUser)) {
        variable_set(self::UPGRADE_CHECK_ACCESS_NAME, $dataUser);
        return TRUE;
      }
    }
    elseif (empty($data) && $param === 'decrypt') {
      $data = variable_get(self::UPGRADE_CHECK_ACCESS_NAME, NULL);
      if (!empty($data)) {
        if (function_exists('openssl_decrypt')) {
          $dataUser = openssl_decrypt($data, self::METHOD, $key, 0, $iv);
        }
        else {
          $dataUser = base64_decode($data);
        }
        return !empty($dataUser) ? unserialize($dataUser) : FALSE;
      }
    }
    return FALSE;
  }

  /**
   * Curl request.
   *
   * @param string $data
   */
  private static function upgradeCheckCurl($data) {
    $headers = array();
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, UPGRADE_CHECK_URL);
    curl_setopt($curl, CURLOPT_VERBOSE, TRUE);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_TIMEOUT, 20);
    curl_setopt($curl, CURLOPT_POST, TRUE);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    $headers[] = 'Content-Type: application/hal+json';
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    $result = curl_exec($curl);
    if (empty($result)) {
      return NULL;
    }
    curl_close($curl);
    return $result;
  }

  /**
   * Crypting value metatag.
   */
  private static function generateCryptMetatag() {
    $salt = variable_get(self::UPGRADE_CHECK_SALT_FIELD_NAME, 'kB3W"jydd');
    $data = md5(drupal_random_key() . $salt);
    return $data;
  }

  /**
   * Crypting values.
   */
  private function generateCryptName($name) {
    $crypt = variable_get(UPGRADE_CHECK_REPLACE_ENTITY_NAME, 'no');
    if (!empty($crypt) && $crypt === 'yes') {
      $salt = variable_get(self::UPGRADE_CHECK_SALT_FIELD_NAME, '');
      $name = md5($name . $salt);
    }
    return $name;
  }

  /**
   * Backport of the DBTNG system_list() from Drupal 7.
   */
  private function systemList($type) {
    $lists =& $this->drupalStatic(__FUNCTION__);
    // For bootstrap modules, attempt to fetch the list from cache if possible.
    // if not fetch only the required information to fire bootstrap hooks
    // in case we are going to serve the page from cache.
    if ($type == 'bootstrap') {
      if (isset($lists['bootstrap'])) {
        return $lists['bootstrap'];
      }
      $query = db_query("SELECT name, filename FROM {system} WHERE status = 1 AND bootstrap = 1 AND type = 'module' ORDER BY weight ASC, name ASC");
      $bootstrap_list = $this->dbFetchAllAssoc($query, 'name');
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
      $query = db_query("SELECT * FROM {system} WHERE type = 'theme' OR (type = 'module' AND status = 1) ORDER BY weight ASC, name ASC");
      while ($record = db_fetch_object($query)) {
        if (!empty($record)) {
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
      }
      foreach ($lists['theme'] as $key => $theme) {
        if (!empty($theme->info['base theme'])) {
          // Make a list of the theme's base themes.
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
   * Backport of the DBTNG drupal_static() from Drupal 7.
   */
  function &drupalStatic($name, $default_value = NULL, $reset = FALSE) {
    static $data = array(), $default = array();
    // First check if dealing with a previously defined static variable.
    if (isset($data[$name]) || array_key_exists($name, $data)) {
      // Non-NULL $name and both $data[$name] and $default[$name] statics exist.
      if ($reset) {
        // Reset pre-existing static variable to its default value.
        $data[$name] = $default[$name];
      }
      return $data[$name];
    }
    // Neither $data[$name] nor $default[$name] static variables exist.
    if (isset($name)) {
      if ($reset) {
        // Reset was called before a default is set and yet a variable must be
        // returned.
        return $data;
      }
      // First call with new non-NULL $name. Initialize a new static variable.
      $default[$name] = $data[$name] = $default_value;
      return $data[$name];
    }
    // Reset all: ($name == NULL). This needs to be done one at a time so that
    // references returned by earlier invocations of drupal_static() also get
    // reset.
    foreach ($default as $name => $value) {
      $data[$name] = $value;
    }
    // As the function returns a reference, the return should always be a
    // variable.
    return $data;
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
