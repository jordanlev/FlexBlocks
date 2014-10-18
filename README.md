Admin interface for creating editable areas and blocks via the PageTableExtended field type.

Probably requires ProcessWire 2.5.2 (for `wireIncludeFile()` function)

**NOTE**: The PageTableExtended module (a requirement of this module) currently has an issue where it does not take into account the "altFilename" setting of templates. Until this issue is addressed, you will need to apply this patch to the PageTableExtended.module file in order for the "FlexBlocks" module to work properly.

##TODO
 * set up cache field to facilitate sane searching of area/block content
 * more robust reporting and handling of file/directory creation errors (as of now, sometimes the template dispatcher file and/or block template subdirectory never wind up getting created upon installation).
 * swap label and handle fields when creating/editing areas and blocks (and use javascript to generate default handle from label [for creates only, not edits])
 * write docs!
 * better reporting of what exactly happened after certain actions (esp. if files/directories were created/renamed/deleted)
 * make sure it works with Repeater fieldtypes and with Fredi
 * translatable strings in code
