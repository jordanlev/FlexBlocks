Admin interface for creating editable areas and blocks via the PageTableExtended field type.

Probably requires ProcessWire 2.5.2 (for `wireIncludeFile()` function)

**NOTE**: You must use version 2.2.1 or higher of the PageTableExtended module (which is a requirement of this module)

##TODO
 * set up cache field to facilitate sane searching of area/block content
 * swap label and handle fields when creating/editing areas and blocks (and use javascript to generate default handle from label [for creates only, not edits])
 * write docs!
 * better reporting of what exactly happened after certain actions (esp. if files/directories were created/renamed/deleted)
 * make sure it works with Repeater fieldtypes and with Fredi
 * translatable strings in code
 * allow sorting of blocks (save the sorted ids array to a module config variable). Eventually allow per-area visibility and sorting?