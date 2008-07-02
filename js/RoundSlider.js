function PostAjax(){
	jQuery.post(location.pathname + "/../admin-ajax.php", {action:"image_shadow_attach", "cookie": encodeURIComponent(document.cookie), "key": this.name, "value": this.value}, function(str){ 
		jQuery("#preview").attr({'src': str}); 
	});	
};
jQuery(document).ready(
	function()
	{
		var centerx = 69;
		var centery = 48;
		var radius = 49;
		myangle = jQuery('#theta').val();
		myangle *= Math.PI/180;
		myangle -= (Math.PI/2);
		x = radius * Math.cos(myangle) + centerx;
		y = radius * Math.sin(myangle) + centery;
		jQuery('#slider').css({ display: 'block' });
		jQuery('#indicator').css({ left: parseInt(x)+'px', top: parseInt(y)+'px', display: 'block' });
		jQuery('#indicator').Draggable(
			{
				onDragModifier : function(x,y)
				{
					var angle = Math.atan((centery-y)/(centerx-x));
					var angle2 = angle;
					if((centerx-x)>=0)
						angle += Math.PI;
					if(centerx>=x)
						angle2 += Math.PI;
					angle2 += (Math.PI/2);
					jQuery('#theta').val(Math.round(360*angle2/(Math.PI*2)));
					return {
						x: radius * Math.cos(angle) + centerx, 
						y: radius * Math.sin(angle) + centery
					}
				},
				onStop: function()
				{
					jQuery('#theta').change();
				}
			}
		);
		jQuery("#shadow_color").attachColorPicker();
		jQuery("#background_color").attachColorPicker();
		jQuery("#frame_color").attachColorPicker();
		jQuery("input:text").bind("change", PostAjax);
	}
);
