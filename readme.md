# Linkbacks #

Contributors:** dshanske  
**Tags:** webmention, pingback, trackback, linkback, indieweb    
**Requires at least:** 4.6    
**Tested up to:** 4.6    
**Stable tag:** 0.1.0    

Linkbacks Redone

## Description ##

This plugin is an attempt to make major changes to linkbacks infrastructure in WordPress. 
It is based off of core code as well as the work previously done by Matthias Pfefferle and
others. It uses the REST API to create a webmention endpoint.

The hope is that this will be a model for a possible inclusion of webmentions and pingbacks
improvements in WordPress core. Any ideas out of this version will hopefully be backported
to the other plugins.

* The plugin attempts to send webmentions, sending pingbacks in the event it does not find a
webmention endpoint, disabling the Core implementation of pingback sending.
* It takes over receiving of webmentions and pingbacks to move them to a common base for as
many subfunctions as possible.
* Uses new register_meta enhancements in 4.6 to declare linkback specific data.
* Supports sending linkbacks for non-posts to an arbitrary post_ID to capture them.
* Supports synchronous and basic asynchronous(delayed verification) for webmentions
* If current storage structure is not used will check Semantic Linkbacks structure for backcompat

## ToDo ##

* Enhancements beyond basic display

## Changelog ##

# Version 0.1.0 #

* Initial Release
