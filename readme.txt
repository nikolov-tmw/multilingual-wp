=== Multilingual WP ===
Contributors: nikolov.tmw
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=S8FGREYJJA4FE
Tags: multilingual, translations, content
Requires at least: 3.5
Tested up to: 3.5.1
Stable tag: 0.1

This plugins allows you to easily integrate multiple language support in your WordPress site.

== Description ==

=== IMPORTANT NOTE ===
From the 25.04.2013 commit, custom post type names have changed drastically. If you've used an older version and you still want to keep all of your translation posts, follow this guide here - https://github.com/nikolov-tmw/multilingual-wp/wiki/Migrating-from-non-hashed-to-hashed-CPT-names 

=== WARNING: ===
This plugin is still in development! It's been tested in production environments for relatively small websites and it's been working good so far. 
This plugin adds translations by creating a new post for every language that you have enabled. So if you have 10 posts and 3 enabled languages, you'll end-up(in your database) with 30 posts. While this seems fine for smaller websites/blogs, I can't guarantee that it will behave properly in larger scale installs. If you have thousands of posts that you want to translate, then this might NOT be the plugin you're looking for.

== Installation ==

Click on the ZIP button above and then extract it to your plugins directory(or extract on your desktop and then upload that directory to the plugins directory). It's recommended to change the directory name to "multilingual-wp" instead of "multilingual-wp-master". Once you do that, go to the plugins page in the Dashboard and enable the plugin. A new menu will show arround the bottom(after the Settings tab) - "Multilingual WP". Click there and configure the plugin's options as desired(the amount of languages enabled, the post types that will be supported, etc). You might want to go to "Multilingual WP > Add New Language" first in order to add new languages. Note that when adding a new language, the plugin will attempt to download the WordPress .mo files for that language, so if you experience some slow-down when adding a new language - then that's probably the case. 

== Changelog ==

= 0.1 =
 * Initial stable release - almost all of the main features are in place with only a couple of them still missing 