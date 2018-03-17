<?php

namespace Upgrade_check;

class EvaluationCode {

  private $version = '8.x';

  private $oldVersion = '6.x';

  private $core = 'core';

  private $custom = 'custom';

  private $contrib = 'contrib';

  private $contribNoUpgrade = 'contrib_no_upgrade';

  private $other = 'other';

  private $regType = '/((\.inc)|(\.php)|(\.module)|(\.install)|(\.test))$/';

  private $regInterface = '/interface\s*(\w+)\s*(\w+\s\w+)*\s\{/';

  private $regComment = '/^(\s*\/+\*+\*+)|(\s+\*+\s+)|(\s+\*+\/+)|(\s+\/+\/+)/';

  private $regClass = '/class\s*(\w+)\s*(\w+\s\w+)*\s\{/';

  /**
   * Implements _upgrade_check_themes_evaluation().
   */
  public function themesEvaluation($theme) {
    $themes = $themes['files'] = array();
    $themes['lines'] = 0;
    $themes['type'] = !empty($theme['status']) ? 'Enabled' : 'Disabled';
    $themes['name'] = !empty($theme['name']) ? $theme['name'] : '';
    $themes['status'] = !empty($theme['status']) ? $theme['status'] : 0;
    $theme_path = drupal_get_path('theme', variable_get('theme_default', NULL));
    $default_theme = substr($theme_path, strrpos($theme_path, '/') + 1);
    if ($themes['name'] === $default_theme) {
      $themes['type'] = 'Default';
    }
    if (!empty($theme['status'])) {
      $param = array($this->custom, $this->contribNoUpgrade);
      $data = $this->updateProcessFetchTask($theme);
      $themes['type_status'] = !empty($data['type']) ? $data['type'] : $this->custom;
      if (!empty($data) && !empty($data['type']) && in_array($data['type'], $param, TRUE)) {
        $filePath = substr($theme['filename'], 0, strripos($theme['filename'], '/'));
        $recursiveDirectory = new \RecursivedirectoryIterator($filePath);
        $recursiveIterator = new \RecursiveIteratorIterator($recursiveDirectory);
        $ident = array('.info', '.txt', '/.', '/..', '.png', '.gif', '.jpeg');
        foreach ($recursiveIterator as $name => $object) {
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
            $checkCode = $this->checkCode($name, $themes['name']);
            $themes['lines'] += $checkCode['all_strings'];
            $checkCode['file_name'] = $name;
            $themes['files'][] = $checkCode;
          }
        }
      }
    }
    $themes['package'] = !empty($theme['info']['package']) ? $theme['info']['package'] : $this->other;
    $themes['project'] = !empty($theme['info']['project']) ? $theme['info']['project'] : '';
    $themes['version'] = !empty($theme['info']['version']) ? $theme['info']['version'] : '';
    return $themes;
  }

  /**
   * Implements _upgrade_check_modules_evaluation().
   */
  public function modulesEvaluation($module) {
    $modules = $modules['files'] = array();
    $modules['lines'] = 0;
    $modules['name'] = !empty($module['name']) ? $module['name'] : '';
    $modules['schema_version'] = !empty($module['schema_version']) ? $module['schema_version'] : '';
    $modules['package'] = !empty($module['info']['package']) ? $module['info']['package'] : $this->other;
    $modules['parent_module'] = !empty($module['parent_module']) ? $module['parent_module'] : '';
    $paramCore = array('Core', 'Core - required', 'Core - optional');
    if (!empty($module['info']['package']) && in_array($module['info']['package'], $paramCore, TRUE)) {
      $modules['type_status'] = $this->core;
    }
    else {
      $param = array($this->custom, $this->contribNoUpgrade);
      $data = $this->updateProcessFetchTask($module);
      $modules['type_status'] = !empty($data['type']) ? $data['type'] : $this->custom;
      $modules['package'] = !empty($module['info']['package']) ? $module['info']['package'] : $this->other;
      if (!empty($data) && !empty($data['type']) && in_array($data['type'], $param, TRUE)) {
        $filePath = substr($module['filename'], 0, strripos($module['filename'], '/'));
        $recursiveDirectory = new \RecursivedirectoryIterator($filePath);
        $recursiveIterator = new \RecursiveIteratorIterator($recursiveDirectory);
        foreach ($recursiveIterator as $name => $object) {
          $status = FALSE;
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
            $checkCode = $this->checkCode($name, $modules['name']);
            $modules['lines'] += $checkCode['all_strings'];
            $checkCode['file_name'] = $name;
            $modules['files'][] = $checkCode;
          }
        }
      }
    }
    $modules['version'] = !empty($module['info']['version']) ? $module['info']['version'] : '';
    return $modules;
  }

  /**
   * Processes a task to fetch available update data for a single project.
   *
   * Once the release history XML data is downloaded, it is parsed and saved
   * into the {cache_update} table in an entry just for that project.
   *
   * @param $data
   *   Associative array of information about the project to fetch data for.
   *
   * @return
   *   array if we fetched parsable XML.
   */
  private function updateProcessFetchTask($data) {
    global $base_url;
    $site_key = md5($base_url . drupal_get_private_key());
    $data['info']['version'] = str_replace($this->oldVersion, $this->version, $data['info']['version']);
    $url = $this->updateBuildFetchUrl($data, $site_key);
    $xml = drupal_http_request($url);
    if (!empty($xml)) {
      $available = $this->parseXml($xml);
    }
    if (!empty($available)) {
      $available['type'] = $this->contrib;
    }
    else {
      $url = $this->updateBuildFetchUrl($data, $site_key, TRUE);
      $xml = drupal_http_request($url);
      if (!empty($xml)) {
        $availableOld = $this->parseXml($xml);
        if (!empty($availableOld)) {
          $available['type'] = $this->contribNoUpgrade;
        }
        else {
          $available['type'] = $this->custom;
        }
      }
      else {
        $available['type'] = $this->custom;
      }
    }
    return !empty($available) ? $available : array();
  }

  /**
   * Generates the URL to fetch information about project updates.
   */
  private function updateBuildFetchUrl($data, $site_key = '', $old = FALSE) {
    $name = $data['name'];
    $url = $this->updateGetFetchUrlBase($data);
    $version = !empty($old) ? $this->oldVersion : $this->version;
    $url .= '/' . $name . '/' . $version;
    if (!empty($site_key) && (strpos($data['type'], 'disabled') === FALSE)) {
      $url .= (strpos($url, '?') !== FALSE) ? '&' : '?';
      $url .= 'site_key=';
      $url .= rawurlencode($site_key);
      if (!empty($data['info']['version'])) {
        $url .= '&version=';
        $url .= rawurlencode($data['info']['version']);
      }
    }
    return $url;
  }

  /**
   * Returns the base of the URL to fetch available update data for a project.
   *
   * @param $project
   *   The array of project information from update_get_projects().
   *
   * @return
   *   The base of the URL used for fetching available update data. This does
   *   not include the path elements to specify a particular project, version,
   *   site_key, etc.
   *
   * @see _update_build_fetch_url()
   */
  private function updateGetFetchUrlBase($project) {
    if (!empty($project['info']['project status url'])) {
      return $project['info']['project status url'];
    }
    return variable_get('update_fetch_url', UPDATE_DEFAULT_URL);
  }

  /**
   * Parses the XML of the Drupal release history info files.
   *
   * @param $xml
   *   A raw XML string of available release data for a given project.
   *
   * @return
   *   Array of parsed data about releases for a given project, and Array if
   *   there was an error parsing the string.
   */
  private function parseXml($xml) {
    if (!isset($xml->error) && !empty($xml->data)) {
      module_load_include('inc', 'update', 'update.fetch');
      $parser = new \update_xml_parser;
      $available = $parser->parse(array($xml->data));
    }
    return !empty($available) ? $available : array();
  }

  /**
   * Check code.
   */
  private function checkCode($file, $name) {
    $allC = $commentC = $codeC = $emptyC = $badEC = 0;
    $functions = $result = array();
    $handle = fopen($file, "r");
    while (!feof($handle)) {
      $content = fgets($handle);
      ++$allC;
      if (preg_match($this->regType, $file)) {
        $this->analyseCode($functions, $content, $name);
      }
      if ($content === "\n" || empty($content)) {
        ++$emptyC;
      }
      elseif ($content === "\r" || $content === "\r\n") {
        ++$badEC;
      }
      elseif (preg_match($this->regComment, $content)) {
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
   * Analyse Code.
   */
  private function analyseCode(&$functions, $content, $name) {
    $regFunction = '/function\s*(_*)(' . $name . '_)*(\w+)\s*\(/';
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
    elseif (preg_match($this->regClass, $content, $class)) {
      if (!empty($class) && !empty($class[1])) {
        $className = $class[1];
        $className .= !empty($class[2]) ? ' ' . $class[2] : '';
        $functions['class'][] = $className;
      }
    }
    elseif (preg_match($this->regClass, $content, $class)) {
      if (!empty($class) && !empty($class[1])) {
        $className = $class[1];
        $className .= !empty($class[2]) ? ' ' . $class[2] : '';
        $functions['class'][] = $className;
      }
    }
    elseif (preg_match($this->regInterface, $content, $interface)) {
      if (!empty($interface) && !empty($interface[1])) {
        $interfaceName = $interface[1];
        $interfaceName .= !empty($interface[2]) ? ' ' . $interface[2] : '';
        $functions['interface'][] = $interfaceName;
      }
    }
    return !empty($functions) ? $functions : '';
  }

  /**
   * Check for submodules.
   */
  public static function upgradeCheckSubmodules($modules) {
    if (!empty($modules)) {
      foreach ($modules as $key => $module) {
        if (!empty($module) && !empty($module->info['dependencies'])) {
          foreach ($module->info['dependencies'] as $dependencies) {
            if (!empty($dependencies) && !empty($modules[$dependencies])) {
              $regSubmodules = '/\/modules\/' . $dependencies . '\/\w+/';
              if (!empty($module->filename) && preg_match($regSubmodules, $module->filename)) {
                $modules[$key]->parent_module = $dependencies;
              }
            }
          }
        }
      }
    }
    return $modules;
  }

  /**
   * Delete info for submodules.
   */
  public function upgradeCheckSubmodulesDeleteInfo($modules) {
    if (!empty($modules)) {
      $modules = $this->upgradeCheckConvertAssociateArray($modules);
      $param = array($this->contrib, $this->core);
      foreach ($modules as $key => $module) {
        if (!empty($module) && !empty($module['parent_module'])) {
          $pKey = $module['parent_module'];
          if (!empty($modules[$pKey]) && !empty($modules[$pKey]['type_status'])
            && in_array($modules[$pKey]['type_status'], $param, TRUE)) {
            $modules[$key]['type_status'] = $modules[$pKey]['type_status'];
            unset($modules[$key]['files']);
          }
        }
      }
    }
    return array_values($modules);
  }

  /**
   * Convert to associate array.
   */
  public function upgradeCheckConvertAssociateArray($datas) {
    if (!empty($datas)) {
      foreach ($datas as $key => $data) {
        if (!empty($data) && !empty($data['name'])) {
          $datas[$data['name']] = $data;
          unset($datas[$key]);
        }
      }
    }
    return $datas;
  }

}
