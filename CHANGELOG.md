# v2.1.2
## 02-02-2021

1. [](#bugfix)
* Fix page image URL Issue #16
* Fix double encoded HTML entities Issue #12

# v2.1.1
## 02-02-2021

1. [](#bugfix)
* Fixed issue with initial save on new pages

# v2.1.0
## 15-12-2020

1. [](#new)
    * Added support for flex pages in Grav 1.7

# v2.0.2
## 03-11-2020

1. [](#bugfix)
    * Fixed bug where custom metadata could not be removed

# v2.0.1
## 07-07-2020

1. [](#bugfix)
    * Added check to include metadata saved prior to v2.0.0

1. [](#bugfix)
    * Updated date published/modified functionality to ensure output of valid timestamp

# v2.0.0
## 23-06-2020

1. [](#improved)
    * Changed the way metadata is stored in frontmatter to capitalise on Grav page caching. **Important:** When upgrading from a previous version existing Aura metadata output will be disabled. You will not be required to re-enter any information, but you will need to actively re-save each page via the page editor to re-enable metadata output.

1. [](#new)
    * Metadata input moved from Options tab to Aura tab in page editor for central editing location and to enable overriding of individual meta tags

1. [](#new)
    * Added support for individual author per page via Aura Authors plugin

1. [](#bugfix)
    * Changed storage location of Organization logo so it will be retained after plugin updates

1. [](#bugfix)
    * Fixed issue with URL extension appearing within page image URL

# v1.0.3
## 29-02-2020

1. [](#bugfix)
    * Adjusted scope of autoloader so it will not interfere with other 'Aura' prefixed plugins

# v1.0.2
## 09-02-2020

1. [](#bugfix)
    * Now appends to existing metadata rather than replacing

# v1.0.1
## 06-09-2019

1. [](#improved)
    * Get language defined in page frontmatter with fallbacks to active language then default language
1. [](#bugfix)
    * Adjusted JSON output to suit Grav versions > 1.5

# v1.0.0
##  21-08-2019

1. [](#new)
    * ChangeLog started...
