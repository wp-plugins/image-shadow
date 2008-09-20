=== Plugin Name ===
Contributors: RobMarsh
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=donate%40rmarsh%2ecom&item_name=Rob%20Marsh%27s%20WordPress%20Plugins&item_number=Image%20Shadow&no_shipping=1&cn=Any%20Comments&tax=0&currency_code=GBP&bn=PP%2dDonationsBF&charset=UTF%2d8
Tags: images, shadow, photos, frames, effects
Requires at least: 1.5
Tested up to: 2.6.2
Stable tag: 1.1.0

Image Shadow automatically adds a soft, realistic drop-shadow to jpeg images in your posts

== Description ==

Some WordPress themes style images frames and drop-shadows. The Image Shadow plugin lets you have such image styling without having to use a special theme. It automatically adds a soft, realistic drop-shadow to the jpeg images in your post content. You can choose the shadow's depth and orientation. You can even add frame to the image. You can let the image grow a shadow or you can ensure that the shadowed image fits in the same space as the original. 

Version 1.1 lets you add shadows to only those images with a given class attribute (the attribute must come after the src attribute). You can also have shadows with a transparent background by leaving the background colour blank.

The shadowed images are cached on your server for fast delivery which means that your PHP setup must allow the creation of new files. You must also have the GD library installed.

**NB** I am aware that the settings screen, though functional, has problems in Internet Explorer.

== Installation ==

Image Shadow is installed in 3 easy steps:

   1. Unzip the "image-shadow" archive and copy the folder to /wp-content/plugins/
   2. Activate the plugin
   3. Use the Settings > Image Shadow admin screen to choose the kind of shadow you want. If you have javascript enabled you will have a live preview of your changes.

== Frequently Asked Questions ==

= None Yet! =

If you want to see the plugin in action please visit [PhotoLinkLove](http://photolinklove.com/). Many thanks to David, the blog owner and photographer, for the inspiration for this plugin.

== Version History ==

* 1.1.0
	* adds option to only shadow images with a specified class attribute
	* if the background colour is left blank the plugin will try to produce a transparent background (requires PHP >=4.3.2)
* 1.0.b2
	* First public release