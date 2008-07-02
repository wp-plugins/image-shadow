<?php
/*
Plugin Name: Image Shadow
Plugin URI: http://rmarsh.com/plugins/image-shadow/
Description: Adds realistic, soft drop-shadows and, optionally, frames to the jpg images in your content.
Version: 1.0.0b2
Author: Rob Marsh, SJ
Author URI: http://rmarsh.com
*/

if ( !defined('WP_CONTENT_URL') ) {
	define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
}

define('IMAGE_SHADOW_OPTIONS', 'image-shadow');

class ImageShadow {
	
	var $options;

	function ImageShadow() {
		register_shutdown_function(array(&$this, "__destruct"));
		$this->__construct();
	}	

	function __construct()	{
		$this->options = get_option(IMAGE_SHADOW_OPTIONS);
		if ($this->options['fail_mkdir']) return; 
		if ($this->options['fail_get_contents']) return; 
		add_filter('the_content', array(&$this, 'filter'), 99);	// we want this to run late in case other plugins insert images
	}
	
	function __destruct() {
	}

	function clear_cache() {
		$cache_directory = $this->options['cache_directory'];
		if (($dir = @opendir($cache_directory)) !== false) {
			while (($file = @readdir($dir)) !== false) {
				$fullname = $cache_directory . DIRECTORY_SEPARATOR . $file;
				if (is_file($fullname)) {
					@unlink($fullname);
				}
			}
		}
	}

	function hex_to_rgb ($hex) {
		if (substr($hex,0,1) == '#') { $hex = substr($hex,1); }
		if (strlen($hex) == 3) {
			$r = $hex[0] . $hex[0];	$g = $hex[1] . $hex[1]; $b = $hex[2] . $hex[2];
		} else {
			$hex .= '000000';
			$r = $hex[0] . $hex[1]; $g = $hex[2] . $hex[3]; $b = $hex[4] . $hex[5];
		}
		$rgb['red']   = hexdec($r); $rgb['green'] = hexdec($g); $rgb['blue']  = hexdec($b);
		return $rgb ;
	}   

	function drawshadow(&$image, $distance, $rectX1, $rectY1, $rectX2, $rectY2, $shadowR, $shadowG, $shadowB, $backR, $backG, $backB) {

		$potentialOverlap = ($distance * 2) * ($distance * 2);

		$backgroundColor = imagecolorallocate($image, $backR, $backG, $backB);
		$shadowColor = imagecolorallocate($image, $shadowR, $shadowG, $shadowB);

		$imageWidth = imagesx($image);
		$imageHeight = imagesy($image);

		imageFilledRectangle($image, 0, 0, $imageWidth - 1, $imageHeight - 1, $backgroundColor);
		imageFilledRectangle($image, $rectX1, $rectY1, $rectX2, $rectY2, $shadowColor);

		for ( $pointX = $rectX1 - $distance; $pointX < $imageWidth; $pointX++ ) {
			for ( $pointY = $rectY1 - $distance; $pointY < $imageHeight; $pointY++ ) {

			if ( $pointX > $rectX1 + $distance && $pointX < $rectX2 - $distance && $pointY > $rectY1 + $distance && $pointY < $rectY2 - $distance ) {
				$pointY = $rectY2 - $distance;
			}

			$boxX1 = $pointX - $distance;
			$boxY1 = $pointY - $distance;
			$boxX2 = $pointX + $distance;
			$boxY2 = $pointY + $distance;

			$xOverlap = max(0, min($boxX2, $rectX2) - max($boxX1, $rectX1));
			$yOverlap = max(0, min($boxY2, $rectY2) - max($boxY1, $rectY1));

			$totalOverlap = $xOverlap * $yOverlap;
			$shadowPcnt = $totalOverlap / $potentialOverlap;
			$backPcnt = 1.0 - $shadowPcnt;

			$newR = $shadowR * $shadowPcnt + $backR * $backPcnt;
			$newG = $shadowG * $shadowPcnt + $backG * $backPcnt;
			$newB = $shadowB * $shadowPcnt + $backB * $backPcnt;

			$newcol = imagecolorallocate($image, $newR, $newG, $newB);
			imagesetpixel($image, $pointX, $pointY, $newcol);
			}
		}
	}
	
	// 3n1gm4 [at] gmail [dot] com -- http://www.php.net/file_get_contents
	function curl_get_contents($URL) {
		$c = curl_init();
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($c, CURLOPT_URL, $URL);
		$contents = curl_exec($c);
		curl_close($c);
		if ($contents) return $contents;
			else return FALSE;
	}
	
	// redvader at yandex dot ru -- http://www.php.net/manual/en/function.fsockopen.php
	function sock_get_contents( $url ) {
	    $url_parsed = parse_url($url);
	    $host = $url_parsed["host"];
	    $port = $url_parsed["port"];
	    if ($port==0)
	        $port = 80;
	    $path = $url_parsed["path"];
	    if ($url_parsed["query"] != "")
	        $path .= "?".$url_parsed["query"];

	    $out = "GET $path HTTP/1.0\r\nHost: $host\r\n\r\n";

	    $fp = fsockopen($host, $port, $errno, $errstr, 30);

	    fwrite($fp, $out);
	    $body = false;
	    while (!feof($fp)) {
	        $s = fgets($fp, 1024);
	        if ( $body )
	            $in .= $s;
	        if ( $s == "\r\n" )
	            $body = true;
	    }
	   
	    fclose($fp);
	   
	    return $in;
	}
	
	function render_effects ($src) {
				
		// see if it is already cached...
		ksort($this->options);
		$filename = md5($src . ':' . implode(':',array_values($this->options)));
		$cache_filename = $this->options['cache_directory'] . DIRECTORY_SEPARATOR . $filename . '.jpg';
		$generated_url = $this->options['cache_url'] . '/' . $filename . '.jpg';
		if (file_exists($cache_filename)) {
			return $generated_url;
		}
		$opacity = $this->options['opacity'];
		$left = -ceil(($this->options['distance'] * sin(deg2rad($this->options['theta']))) / tan(deg2rad($this->options['alpha'])));
		$top = ceil(($this->options['distance'] * cos(deg2rad($this->options['theta']))) / tan(deg2rad($this->options['alpha'])));
		$blur_radius = ceil($this->options['blurring'] * $this->options['distance']);
		$shadow_color = $this->options['shadow_color'];
		$background_color = $this->options['background_color'];
		$frame_color = $this->options['frame_color'];
		$frame_width = $this->options['frame_width'];
		$preserve = $this->options['preserve'];

		$origin_x = ($left > 0) ? max($blur_radius, $left) : $blur_radius;
		$origin_y = ($top > 0) ? max($blur_radius, $top) : $blur_radius;
		
		$overhang_x = ($left > 0) ? $blur_radius : max($blur_radius, abs($left));
		$overhang_y = ($top > 0) ? $blur_radius : max($blur_radius, abs($top));

		// only work on jpeg images
		if (!preg_match('/\.(jpeg|jpg|jpe)$/i', $src)) return $src;
		switch ($this->options['get_contents']) {
		case 'file':
			$src_data = @file_get_contents($src);
			break;
		case 'curl':
			$src_data = curl_get_contents($src);
			break;
		case 'sock':
			$src_data = sock_get_contents($src);
			break;
		default:
			$src_data = file_get_contents($src);
		}
		if (!$src_data) return $src;
		$original_image = imagecreatefromstring($src_data);
		unset($src_data);
	
		// calculate the dimensions of the final image ...
		$original_width = imagesx($original_image) ;
		$original_height = imagesy($original_image);
		$width = $original_width + $origin_x + $overhang_x + 2*$frame_width;
		$height = $original_height + $origin_y + $overhang_y + 2*$frame_width;
		// ... and create it
		$image = imagecreatetruecolor($width, $height);
		
		// draw shadow
		$shadow_rgb = $this->hex_to_rgb($shadow_color);
		$background_rgb = $this->hex_to_rgb($background_color);
		foreach($background_rgb as $rgb => $value) {
			$shadow_rgb[$rgb] = $opacity*$shadow_rgb[$rgb] + (1-$opacity)*$value;
		}
		$this->drawshadow($image, $blur_radius, $origin_x, $origin_y, $origin_x+$original_width+2*$frame_width, $origin_y+$original_height+2*$frame_width, $shadow_rgb['red'], $shadow_rgb['green'], $shadow_rgb['blue'], $background_rgb['red'], $background_rgb['green'], $background_rgb['blue']);

		// draw frame
		if ($frame_width > 0) {
			$frame_rgb = $this->hex_to_rgb($frame_color);
			$framecolor = imagecolorallocate($image, $frame_rgb['red'], $frame_rgb['green'], $frame_rgb['blue']);
			imagefilledrectangle($image, $origin_x-$left, $origin_y-$top, $origin_x-$left+$original_width + 2*$frame_width - 1, $origin_y-$top+$original_height + 2*$frame_width - 1, $framecolor);
			imagerectangle($image, $origin_x-$left, $origin_y-$top, $origin_x-$left+$original_width + 2*$frame_width - 1, $origin_y-$top+$original_height + 2*$frame_width - 1, imagecolorallocate($image, 200,200,200));
		}
		
		// draw image on top
		imagecopy($image, $original_image, $origin_x-$left+$frame_width, $origin_y-$top+$frame_width, 0, 0, $original_width, $original_height);
		
		// if required, resize the final image
		if ($this->options['preserve'] === 'width') {
			$new_height = $original_width * ($height / $width);
			$newimage = imagecreatetruecolor($original_width, $new_height);
			imagecopyresampled($newimage, $image, 0, 0, 0, 0, $original_width, $new_height, $width, $height);
			imagejpeg($newimage, $cache_filename, 100);
			imagedestroy($newimage);
		} else if ($this->options['preserve'] === 'height') {
			$new_width = $original_height * ($width / $height);
			$newimage = imagecreatetruecolor($new_width, $original_height);
			imagecopyresampled($newimage, $image, 0, 0, 0, 0, $new_width, $original_height, $width, $height);	
			imagejpeg($newimage, $cache_filename, 100);
			imagedestroy($newimage);
		} else {
			imagejpeg($image, $cache_filename, 100);	
		}
		
		// tidy up
		imagedestroy($image);
		imagedestroy($original_image);
		// return the link to the image
		if (file_exists($cache_filename)) {
			return $generated_url;
		} else {
			return $src;
		}
	}	
	
	function filter($content) {
		preg_match_all('#<img.+?src\s*=\s*["|\'](.+?)["|\'].+?>#isu', $content, $matches);
		foreach($matches[1] as $src) {
			$newsrc[] = $this->render_effects($src);
		}
 		$content = str_replace($matches[1], $newsrc, $content);
 		return $content;
	}
	
	function activate() {
		// default settings
		$options = array();
		$options['cache_directory'] = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'cache';
		$options['cache_url'] = WP_CONTENT_URL . '/plugins/' . dirname(plugin_basename(__FILE__)) . '/cache';
		if (!isset($options['opacity'])) $options['opacity'] = 0.8;
		if (!isset($options['theta'])) $options['theta'] = 315;
		if (!isset($options['alpha'])) $options['alpha'] = 70;
		if (!isset($options['distance'])) $options['distance'] = 10;
		if (!isset($options['blurring'])) $options['blurring'] = 1.0;
		if (!isset($options['shadow_color'])) $options['shadow_color'] = '#000';
		if (!isset($options['background_color'])) $options['background_color'] = '#fff';
		if (!isset($options['frame_color'])) $options['frame_color'] = '#fff';
		if (!isset($options['frame_width'])) $options['frame_width'] = 0;
		if (!isset($options['preserve'])) $options['preserve'] = 'false';
		// try to create the cache directory
		if (!is_dir($options['cache_directory'])) {
			if (!@mkdir($options['cache_directory'], 0777)) $options['fail_mkdir'] = true;
		}
		// try to find a working method of fetching an image from a url
		if (ini_get('allow_url_fopen')) {
			$options['get_contents'] = 'file';
		} else if (function_exists('curl_init')) {
			$options['get_contents'] = 'curl';
		} elseif (function_exists('fsockopen')) {
			$options['get_contents'] = 'sock';
		} else {
			$options['fail_get_contents'] = true;
		}
		update_option(IMAGE_SHADOW_OPTIONS, $options);
	}

	function deactivate() {
		delete_option(IMAGE_SHADOW_OPTIONS);
	}

	function admin_head() {
		wp_enqueue_script('interface');
		wp_enqueue_script('picker', WP_CONTENT_URL.'/plugins/image-shadow/js/ColorPicker.js', array('jquery'),'1.0');
		wp_enqueue_script('round', WP_CONTENT_URL.'/plugins/image-shadow/js/RoundSlider.js', array('jquery'),'1.0');
		echo '<link media="all" type="text/css" href="'.WP_CONTENT_URL.'/plugins/image-shadow/js/ColorPicker.css" rel="stylesheet" />'."\n";
		echo '<link media="all" type="text/css" href="'.WP_CONTENT_URL.'/plugins/image-shadow/js/RoundSlider.css" rel="stylesheet" />'."\n";
	}
	
	function option_menu() {
		$options_page = add_options_page(__('Image Shadow Options', 'image_shadow'), __('Image Shadow', 'image_shadow'), 8, 'image_shadow', array('ImageShadow', 'options_page'));
		add_action( "admin_print_scripts-$options_page", array('ImageShadow', 'admin_head'));
	}
	
	function options_page() {
		$options = get_option(IMAGE_SHADOW_OPTIONS);
	//$options['fail_mkdir'] = true;
		if ($options['fail_mkdir']) {
			$safe = ini_get('safe_mode');
			echo '<div class="updated fade"><p>' . __('ImageShadow could not create the cache directory. Check the write permissions of the plugin directory. You may have some luck creating the cache directory by hand.', 'image_shadow') . '</p></div>';
			return;
		}
		if ($options['fail_get_contents']) {
			echo '<div class="updated fade"><p>' . __('ImageShadow could not find a way of opening remote images') . '</p></div>';
			return;
		}	
		if (isset($_POST['update_options'])) {
			check_admin_referer('image_shadow_update_options'); 
			if(isset($_POST['opacity'])) $options['opacity'] = floatval($_POST['opacity']);
			if ($options['opacity'] < 0) $options['opacity'] = 0;
			if ($options['opacity'] > 1) $options['opacity'] = 1;
			if(isset($_POST['theta'])) $options['theta'] = intval($_POST['theta']);
			if ($options['theta'] < 0) $options['theta'] = 0;
			if ($options['theta'] > 360) $options['theta'] = 360;
			if(isset($_POST['alpha'])) $options['alpha'] = intval($_POST['alpha']);
			if ($options['alpha'] < 10) $options['alpha'] = 10;
			if ($options['alpha'] > 90) $options['alpha'] = 90;
			if(isset($_POST['distance'])) $options['distance'] = intval($_POST['distance']);
			if ($options['distance'] < 0) $options['distance'] = 0;
			if(isset($_POST['blurring'])) $options['blurring'] = floatval($_POST['blurring']);
			if ($options['blurring'] <= 0) $options['blurring'] = 0.01;
			if ($options['blurring'] > 10) $options['blurring'] = 10;
			if(isset($_POST['shadow_color'])) $options['shadow_color'] = $_POST['shadow_color'];
			if(isset($_POST['background_color'])) $options['background_color'] = $_POST['background_color'];
			if(isset($_POST['frame_color'])) $options['frame_color'] = $_POST['frame_color'];
			if(isset($_POST['frame_width'])) $options['frame_width'] = intval($_POST['frame_width']);
			if ($options['frame_width'] < 0) $options['frame_width'] = 0;
			if(isset($_POST['preserve'])) $options['preserve'] = $_POST['preserve'];
			update_option(IMAGE_SHADOW_OPTIONS, $options);
			global $ImageShadow; $ImageShadow->clear_cache();
			echo '<div class="updated fade"><p>' . __('Settings saved', 'image_shadow') . '</p></div>';
		} 
		$preview = WP_CONTENT_URL . '/plugins/' . dirname(plugin_basename(__FILE__)) . '/js/preview.jpg';
		$PreviewImageShadow = new ImageShadow(); 
		$preview = $PreviewImageShadow->render_effects($preview);
		unset($PreviewImageShadow);
		?>
			<div class="wrap">
			<h2><?php _e('Settings', 'image_shadow'); ?></h2>
			<img id="preview" src="<?php echo $preview; ?>" alt="" title="preview with settings">
			<form method="post" action="">
			<div class="submit"><input type="submit" name="update_options" value="<?php _e('Save Settings', 'image_shadow') ?>" /></div>
			<table class="optiontable form-table">
				<tr valign="top">
					<th scope="row"><?php _e('Opacity of shadow (between 0.0 and 1.0):', 'image_shadow') ?></th>
					<td><input name="opacity" type="text" id="opacity" value="<?php echo $options['opacity']; ?>" size="3" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('Orientation of light source (degrees):', 'image_shadow') ?></th>
					<td style="height:120px;">
					<div id="circle"><div id="slider"></div><div id="indicator"></div></div>
					<input name="theta" type="text" id="theta" value="<?php echo $options['theta']; ?>" size="3" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('Vertical angle of light source (degrees):', 'image_shadow') ?></th>
					<td><input name="alpha" type="text" id="alpha" value="<?php echo $options['alpha']; ?>" size="3" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('Apparent distance of image from page (px):', 'image_shadow') ?></th>
					<td><input name="distance" type="text" id="distance" value="<?php echo $options['distance']; ?>" size="3" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('Blurriness of shadow:', 'image_shadow') ?></th>
					<td><input name="blurring" type="text" id="blurring" value="<?php echo $options['blurring']; ?>" size="3" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('Colour of shadow (e.g., #000000):', 'image_shadow') ?></th>
					<td><input name="shadow_color" type="text" id="shadow_color" value="<?php echo $options['shadow_color']; ?>" size="7" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('Colour of background (e.g., #FFFFFF):', 'image_shadow') ?></th>
					<td><input name="background_color" type="text" id="background_color" value="<?php echo $options['background_color']; ?>" size="7" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('Colour of frame (e.g., #FFFFFF):', 'image_shadow') ?></th>
					<td><input name="frame_color" type="text" id="frame_color" value="<?php echo $options['frame_color']; ?>" size="7" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('Width of frame (px):', 'image_shadow') ?></th>
					<td><input name="frame_width" type="text" id="frame_width" value="<?php echo $options['frame_width']; ?>" size="3" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('Final image size?', 'image_shadow') ?></th>
					<td>
						<select name="preserve" id="preserve">
						<option <?php if($options['preserve'] == 'false') { echo 'selected="selected"'; } ?> value="false">Let shadow etc. make new image bigger</option>
						<option <?php if($options['preserve'] == 'width') { echo 'selected="selected"'; } ?> value="width">Shrink new image to fit in original width</option>
						<option <?php if($options['preserve'] == 'height') { echo 'selected="selected"'; } ?> value="height">Shrink new image to fit in original height</option>
						</select>
					</td> 
				</tr>
			</table>
			<div class="submit"><input type="submit" name="update_options" value="<?php _e('Save Settings', 'image_shadow') ?>" /></div>
			<?php if (function_exists('wp_nonce_field')) wp_nonce_field('image_shadow_update_options'); ?>
			</form>  
		</div>
		<?php	
	}

}

add_action('activate_'.plugin_basename(__FILE__), array('ImageShadow', 'activate'));
add_action('deactivate_'.plugin_basename(__FILE__), array('ImageShadow', 'deactivate'));
add_action('admin_menu', array('ImageShadow', 'option_menu'));
add_action('plugins_loaded', create_function('', 'global $ImageShadow; $ImageShadow = new ImageShadow();'));
function ajaxresponse() {
	$preview = WP_CONTENT_URL . '/plugins/' . dirname(plugin_basename(__FILE__)) . '/js/preview.jpg';
	$key = $_POST['key'];
	$value = $_POST['value'];
	$options = get_option(IMAGE_SHADOW_OPTIONS);
	if ($key === 'opacity' && $value < 0) $value = 0;
	if ($key === 'opacity' && $value > 1) $value = 1;
	if ($key === 'theta' && $value < 0) $value = 0;
	if ($key === 'theta' && $value > 360) $value = 360;
	if ($key === 'alpha' && $value < 10) $value = 10;
	if ($key === 'alpha' && $value > 90) $value = 90;
	if ($key === 'distance' && $value < 0) $value = 0;
	if ($key === 'blurring' && $value <= 0) $value = 0.01;
	if ($key === 'blurring' && $value > 10) $value = 10.0;
	if ($key === 'frame_width' && $value < 0) $value = 0;
	$options[$key] = $value;
	update_option(IMAGE_SHADOW_OPTIONS, $options);
	$PreviewImageShadow = new ImageShadow(); 
	$PreviewImageShadow->clear_cache();
	$preview = $PreviewImageShadow->render_effects($preview);
	unset($PreviewImageShadow);
	echo $preview;
	exit;
}
add_action('wp_ajax_image_shadow_attach', 'ajaxresponse');

?>
