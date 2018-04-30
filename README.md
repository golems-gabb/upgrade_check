# Introduction

**Upgrade Check module allows quickly estimate Drupal project
 for migration to Drupal 8. Available versions of the module Drupal 6 and Drupal 7 versions.**

**Module analyzes project compatibility, prepare data and show a detailed report
 on the service site.**
 
 **We care about protecting your personal information, so you can encode data (content type names, vocabulary term manes, field names, etc).
It is also important that if you estimate your web resource by registering, your result estimate will only be available to you. No third party will be able to see this information.** 

The advantages of our module over similar modules are that we analyze your resource in more detail and quality. On the registration service is available on the service site. This allows you to see all your estimated sites in one place without worrying about finding information. The next version of the module will allow automatic synchronization of information from your website to the service site.

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
   "Drupal 8 upgrade evaluation" (admin/config/upgrade-check/evaluation)
4. Once the module has loaded click "Analyze".
5. Now this module will start analysing your website.
6. Once module has completed analysis, press "Download JSON".
7. After downloading JSON File, upload it on https://golems.top/estimate
   with your credentials (you have to login or register) to check your website report. Available version of estimate web resource without authorization. But in this case, the estimates will be kept for a limited time.
8. Your estimated resources will be available on your profile page on the service site. Also, a link will be sent to your e-mail, which the assessment of your web resource will be available.

### Contact info

[test contact](mailto:test@test.com)
//Upload Json
