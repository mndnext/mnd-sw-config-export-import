# Config Export Import for Shopware

This Shopware ([https://www.shopware.com](https://www.shopware.com)) Plugin extends the Shopware CLI with commands to export and import shopware configuration data to and from xml-files.

The primary purpose ist to make shopware configuration data and settings manageable in version control like git. Further it makes it possible to edit e.g. mail and product feed templates in files, so you can use your IDE and don't have to fight with the shopware backend masks.

The export files should provide a structure to get useful diff views in IDEs to compare different versions of configs. E.g. to see what are the differences in development and production environment.
  
Also useful for deployment and backup purposes.

## What can be exported and imported?

Please note: This is work in progress. If you use this in a production environment please make sure everything works as expected.

##### Core Config of Shopware and plugin configurations
(Grundeinstellungen und Plugin-Einstellungen)

`mnd:swconfig:export` / `mnd:swconfig:import`

##### Documents
(Belege)

`mnd:swdocument:export` / `mnd:swdocument:import`

##### Content Pages
(Inhaltsseiten)

`mnd:swcms:export` / `mnd:swcms:import`

##### Forms
(Formulare)

`mnd:swform:export` / `mnd:swform:import`

##### Mail
(Mailvorlagen)

`mnd:swmail:export` / `mnd:swmail:import`

##### Plugin states
(Plugin Zustände (installiert, aktiviert etc.))

`mnd:swplugin:export` / `mnd:swplugin:import`


##### Productfeeds
(Produktexporte für Marktplätze etc.)


`mnd:swproductfeed:export` / `mnd:swproductfeed:import`

##### Theme config
(Theme configuration)

`mnd:swthemeconfig:export` / `mnd:swthemeconfig:import`


## Compatibility

Should work with Shopware 5.0 and above.

## Usage

Place Plugin code in new folder `engine/Shopware/Plugins/Community/Core/MndConfigExportImport/` and install + activate via Plugin Manager or CLI

When calling the console you should see new commands an the overview, like this:

```
 $ bin/console
 (...)
 mnd
  mnd:swall:export                           Export all to directory /<absolutepath>/_mndExport/ or custom target via -p
  mnd:swcms:export                           Export Cms pages to directory /<absolutepath>/_mndExport/ or custom target via -p
  mnd:swcms:import                           Import Sites from directory /<absolutepath>/_mndExport/ or custom source via -p
  mnd:swconfig:export                        Export Shopware Config to directory /<absolutepath>/_mndExport/ or custom target via -p
  mnd:swconfig:import                        Import Shopware Config from directory /<absolutepath>/_mndExport/ or custom source via -p
  mnd:swdocument:export                      Export documents to directory /<absolutepath>/_mndExport/ or custom target via -p
  mnd:swdocument:import                      Import documents from directory /<absolutepath>/_mndExport/ or custom source via -p
  mnd:swform:export                          Export form pages to directory /<absolutepath>/_mndExport/ or custom target via -p
  mnd:swform:import                          Import forms from directory /<absolutepath>/_mndExport/ or custom source via -p
  mnd:swmail:export                          Export Mail Templates to directory /<absolutepath>/_mndExport/ or custom target via -p
  mnd:swmail:import                          Import Mail Templates from directory /<absolutepath>/_mndExport/ or custom source via -p
  mnd:swplugin:restore                       Restore plugin states from directory /<absolutepath>/_mndExport/ or custom source via -p
  mnd:swplugin:save                          Save current state of plugins to directory /<absolutepath>/_mndExport/ or custom target via -p
  mnd:swproductfeed:export                   Export Shopware ProductFeeds to directory /<absolutepath>/_mndExport/ or custom target via -p
  mnd:swproductfeed:import                   Import ProductFeeds from directory /<absolutepath>/_mndExport/ or custom source via -p
  mnd:swthemeconfig:export                   Export Theme Config to directory /<absolutepath>/_mndExport/ or custom target via -p
  mnd:swthemeconfig:import                   Export Theme Config to directory /<absolutepath>/_mndExport/ or custom target via -p
(...)
```

use correspondig command to export what you wish. To export all possible elements use  `mnd:swall:export`

### Changing target directory

You can change target directory via the parameter -p

### Overwriting existing export

The script detects if an export already exists. Use parameter -c to overwrite an existing export

## directory structure

```
_mndExport/
|-- cms
|   |-- config.xml
|   `-- templates
|       |-- 1_Kontakt.html
        (...)
        
|-- config
|   |-- 1_Shopname.xml
|   `-- 3_SubshopName.xml

|-- document
|   |-- 1_Rechnung
|   |   |-- Body.html
|   |   |-- Content.html
|   |   |-- Content_Amount.html
|   |   |-- Content_Info.html
|   |   |-- Footer.html
|   |   |-- Header.html
|   |   |-- Header_Box_Bottom.html
|   |   |-- Header_Box_Left.html
|   |   |-- Header_Box_Right.html
|   |   |-- Header_Recipient.html
|   |   |-- Header_Sender.html
|   |   |-- Logo.html
|   |   |-- Td.html
|   |   |-- Td_Head.html
|   |   |-- Td_Line.html
|   |   |-- Td_Name.html
|   |   `-- config.xml
    (...)

|-- form
|   |-- 18_Contact
|   |   |-- Contact.config.xml
|   |   |-- Contact_confirmation.html
|   |   |-- Contact_header.html
|   |   `-- Contact_template.html
    (...)
    
|-- mail
|   |-- sORDER
|   |   |-- attachments
|   |   |   `-- 1
|   |   |       `-- Attachment.pdf
|   |   |-- config.xml
|   |   |-- content.1.html.tpl
|   |   `-- content.1.txt.tpl
    (...)
    
|-- plugin
|   `-- config.xml

|-- productfeed
|   |-- Google\ Produktsuche
|   |   |-- body.html
|   |   |-- config.xml
|   |   |-- filter.sql
|   |   |-- footer.html
|   |   `-- header.html
    (...)
    
`-- themeconfig
    |-- Bare
    |   |-- Elements.config.xml
    |   |-- Layouts
    |   |   |-- Icons.xml
    |   |   |-- bareLogos.xml
    |   |   |-- bareMain.xml
    |   |   `-- main_container.xml
    |   |-- Sets
    |   |   |-- __bare_max_appearance__.xml
    |   |   `-- __bare_min_appearance__.xml
    |   `-- config.xml
    
    |-- Responsive
    |   |-- Elements.config.xml
    |   |-- Layouts
    |   |   |-- Icons.xml
    |   |   |-- badges_fieldset.xml
    |   |   |-- bareGlobal.xml
    |   |   |-- bareLogos.xml
    |   |   |-- bareMain.xml
    |   |   |-- basic_field_set.xml
    |   |   |-- bottom_tab_panel.xml
    |   |   |-- buttons_default_fieldset.xml
    |   |   |-- buttons_fieldset.xml
    |   |   |-- buttons_primary_fieldset.xml
    |   |   |-- buttons_secondary_fieldset.xml
    |   |   |-- buttons_tab.xml
    |   |   |-- form_base_fieldset.xml
    |   |   |-- form_states_fieldset.xml
    |   |   |-- forms_tab.xml
    |   |   |-- general_tab.xml
    |   |   |-- grey_tones.xml
    |   |   |-- highlight_colors.xml
    |   |   |-- labels_fieldset.xml
    |   |   |-- main_container.xml
    |   |   |-- panels_fieldset.xml
    |   |   |-- responsiveGlobal.xml
    |   |   |-- responsiveMain.xml
    |   |   |-- responsive_tab.xml
    |   |   |-- scaffolding.xml
    |   |   |-- tables_fieldset.xml
    |   |   |-- tables_tab.xml
    |   |   |-- typo_base.xml
    |   |   |-- typo_headlines.xml
    |   |   `-- typo_tab.xml
    |   |-- Sets
    |   |   |-- __color_scheme_black__.xml
    |   |   |-- __color_scheme_blue__.xml
    |   |   |-- __color_scheme_brown__.xml
    |   |   |-- __color_scheme_gray__.xml
    |   |   |-- __color_scheme_green__.xml
    |   |   |-- __color_scheme_orange__.xml
    |   |   |-- __color_scheme_pink__.xml
    |   |   |-- __color_scheme_red__.xml
    |   |   `-- __color_scheme_turquoise__.xml
    |   `-- config.xml
    (...)
```

## Known limits
Currently, imports will not create clean states as not known objects will not be deleted. Imports only update or create rows.

Parameter -c is needed for exports in most cases.

Import of mails will only update existing ones.

Import of mail attachments doesn't work.

Plugin states only exports installation and active states for every plugin. No plugin files will be copied.
Plugin states only restores installation and active states for plugins that already exists in target database.

ThemeConfig identifies shops through given host.

Imported Productfeeds will always have "Expire" set to now and "LastChange" set to null.

Import of ShopConfig only updates existing configurations. Plugin relations will not be checked.
