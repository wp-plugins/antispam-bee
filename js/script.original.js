jQuery(document).ready(
	function($) {
		/* Init */
		var ab_num, ab_obj = [
			'antispam_bee_flag_spam',
			'antispam_bee_country_code',
			'antispam_bee_honey_pot',
			'antispam_bee_translate_api'
		];
		
		/* Checkboxen markieren */
		function manage_options(checkbox) {
			var obj = $(checkbox).parents('li').find('.shift');
			
			obj.slideToggle(
				'fast',
				function() {
					obj.children().find(':input').attr('disabled', !$(checkbox).attr('checked'));
				}
			);
		}
		
		/* Event zuweisen */
		for (ab_num in ab_obj) {
			$('#' + ab_obj[ab_num]).click(
				function() {
					manage_options(this);
				}
			);
		}
	}
);
