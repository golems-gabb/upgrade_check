# Introduction

**Upgrade Check module allows quickly estimate Drupal project
 for migration to Drupal 8.**

**Module analyzes project compatibility, prepare data and show detailed report
 on the service site.**

## Usage
You need to do few steps to generate report:
1. Download the module. Use the optimal way to install that fit
    your project module delivery method via manual installing, drush or composer:
    ```
    # Manual way
    #   1. Download module tarball on the project page
    #   https://www.drupal.org/project/upgrade_check
    
    # Drush way
    drush dl upgrade_check -y
    
    # Composer way
    composer require drupal/upgrade_check
    ```
2. Enable module via admin UI or Drush. 
3. After installatin go to configuration tab and find
   "Drupal 8 upgrade evaluation" (admin/config/upgrade-check/json-download)
4. Once the module has loaded click "Generate JSON".
5. Now this module will start analysing your website.
6. Once module has completed analysis, press "Download JSON file".
7. After downloading JSON File, upload it on https://site.com/evaluation/upgrade/
   with your credentials to check your website report.

### Contact info

[test contact](mailto:test@test.com)