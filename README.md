# Introduction
  * Upgrade Check module allows quickly estimate Drupal project for migration
  to Drupal 8.
  * Versions of the module are available for Drupal 6 and Drupal 7 versions.
  * Module analyzes project compatibility, prepares data and shows
  a detailed report on the service site.

## Why should you upgrade your site to Drupal 8? Answers are below:
  * The sites for Drupal 6 and Drupal 7 in the future will be more expensive
  in support.
  * The sites for Drupal 6 and Drupal 7 are less secure.
  You risk losing your personal data and, accordingly, your money.
  * Drupal 8 has more mobile friendly and responsive theme.
  * Drupal 8 has better localization options.
  * Drupal 8 is easier for content creation and management.
  You will spend less money to support your resource.
  * Drupal 8 has better performance.
  * Drupal 8 has boosted your SEO and traffic.
  This will increase your revenue and reduce the cost of SEO.
  * All best practices and technologies are used in Drupal 8.
  * And more...

## The advantages of our module over similar modules are:
  * We analyze your resource in more details and more qualitatively
  than other modules.
  * We care about protecting your personal information, so you can encode data
  (content type names, vocabulary term manes, field names, etc).
  It is also important that if you estimate your web resource by registering,
  your estimation result will only be available to you. No third side
  will be able to see this information.
  * All estimation results are available on the service site. This allows you to
  see all your estimated sites in one place without worrying about finding
  information. The next version of the module will allow to synchronize
  automatically the information from your website to the service site.
  * The estimation of your web resource upgrading is absolutely free.
  * We are constantly developing and improving this module.
  * Our company is always in touch and will help to solve any problem
  with the module, and we always answer your questions.

## Preparation
  To estimate migration as accurately as possible, follow these tips:
  1. Please disable modules and themes that are not used on the site.
     Because they increase the time for migration.
  2. Delete all entities: users, nodes, comments, files, taxonomy terms,
     that you do not use.
     Because they increase the time for migration.
  3. If you have enabled the "Background Process" module, disable it.
     Because there is a compatibility issue for this modules.

## Usage
  You need to take few steps to generate the report:
  1. Download the module. Use the optimal way to install that fits your project
  module delivery method via manual installing, drush or composer:

    ### Manual way
      Download module "Drupal 8 upgrade evaluation" on the project page
      https://www.drupal.org/project/upgrade_check

    ### Drush way
      drush dl upgrade_check -y

    ### Composer way
      composer require drupal/upgrade_check

  2. Enable module via admin UI or Drush.
  3. After installation go to configuration tab and find
     "Drupal 8 upgrade evaluation" (admin/config/upgrade-check/evaluation)
  4. If you need to be able to work with the module for other user roles
     on the site, go to page "admin/people/permissions"
     and add the "administer upgrade check" permissions to the required role.
  5. Once the module has loaded click "Analyze".
  6. Now, this module will start analyzing your website.
  7. Once the module has completed the analysis, press "Download JSON".
  8. **Do not disable the "Drupal 8 upgrade evaluation" module until you
     download json file to resource https://golems.top/estimate and do not get
     estimate result. Because the "Drupal 8 upgrade evaluation" module is
     needed to confirm verifying the ownership of your website.**
  9. After downloading JSON File, for manual way, upload it on
    https://golems.top/estimate with your credentials
    (you have to log in or register) to estimate your website. There is an
    available version to estimate your web resource without authorization.
    But in this case, the estimation result will be kept for a limited time.
    **When automatic synchronization will be available, simply go to the
    service site https://golems.top. Log in, using access data that will be
    available on "/admin/config/upgrade-check/result" page of your site.
    Estimation results will be available on resourse https://golems.top on the
    profile page.**
  10. Your estimated resources will be available on your profile page on the
    service site. Also, a link will be sent to your e-mail, where the assessment
    of your web resource will be available.
  11. Enjoy the result.

### Contact info

[golems.sup@gmail.com](golems.sup@gmail.com)

### Maintainers

 * Oleksandr Bazyliuk(alex_optim) https://www.drupal.org/u/alex_optim
 * Oleh Vehera(voleger) https://www.drupal.org/u/voleger
 * Oleksandr Dekhteruk(pifagor) https://www.drupal.org/u/pifagor
