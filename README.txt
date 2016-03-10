CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Installation
 * Configuration
 * Usage
 * How Can You Contribute?
 * Maintainers


INTRODUCTION
------------

Author and maintainer: Pawel Ginalski (gbyte.co) https://www.drupal.org/u/gbyte.co

The module generates a multilingual XML sitemap which adheres to Google's new
hreflang standard. Out of the box the sitemap is able to index the following
content:

 * nodes
 * taxonomy terms
 * menu links
 * users
 * custom links

The above functionalities are implemented as Drupal 8 plugins and it is easy to
add support to custom entity types through implementing your own plugins.

To learn about XML sitemaps, see https://en.wikipedia.org/wiki/Sitemaps.


INSTALLATION
----------

See https://www.drupal.org/documentation/install/modules-themes/modules-8
for instructions on how to install or update Drupal modules.


CONFIGURATION
----------

The module permission 'administer sitemap settings' can be configured under
/admin/people/permissions.

Initially only the home page is indexed in the sitemap. To include content into
the sitemap, visit the corresponding entity type edit pages, e.g.

 * /admin/structure/types/manage/[content type] for nodes,
 * /admin/structure/taxonomy/manage/[taxonomy vocabulary] for taxonomy terms,
 * /admin/structure/menu/manage/[menu] for menu items,
 * /admin/config/people/accounts for users

When including an entity type into the sitemap, the priority setting can be set
which will set the 'priority' parameter for all entities of that type. See
https://en.wikipedia.org/wiki/Sitemaps to learn more about this parameter.

If you wish for the sitemap to reflect the new configuration instantly, check
'Regenerate sitemap after clicking save'. This setting only appears if a change
in the settings has been detected.

As the sitemap is accessible to anonymous users, bear in mind that only links
will be included which are accessible to anonymous users.

To include custom links into the sitemap, visit
/admin/config/search/simplesitemap/custom.

The settings page can be found under admin/config/search/simplesitemap.
Here the module can be configured and the sitemap can be manually regenerated.


USAGE
----------

The sitemap is accessible to the whole world under /sitemap.xml.

If the cron generation is turned on, the sitemap will be regenerated on every
cron run.

A manual generation is possible on admin/config/search/simplesitemap.

The sitemap can be also generated via drush: Use the command
'drush simple_sitemap-generate'.


HOW CAN YOU CONTRIBUTE?
-----------------------

 * Report any bugs, feature or support requests in the issue tracker, if possible
   help out by submitting patches.
   http://drupal.org/project/issues/simple_sitemap

 * Do you know a non-English language? Help translating the module.
   https://localize.drupal.org/translate/projects/simple_sitemap

 * If you would like to say thanks and support the development of this module, a
   donation is always appreciated.
   https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=5AFYRSBLGSC3W


MAINTAINERS
-----------

Current maintainers:
 * Pawel Ginalski (gbyte.co) - https://www.drupal.org/u/gbyte.co
