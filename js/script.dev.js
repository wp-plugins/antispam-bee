jQuery(document).ready(
	function($) {
		/* Mini Plugin */
		$.fn.abManageOptions = function() {
			var $$ = this,
				obj = $$.parents('tr').nextAll('tr');
				
			obj.toggle(
				0,
				function() {
					obj.children().find(':input').attr('disabled', !$$.attr('checked'));
				}
			);
		}
		
		/* Event abwickeln */
		$('#ab_main .related tr:first-child :checkbox').click(
			function() {
				$(this).abManageOptions();
			}
		).filter(':checked').abManageOptions();
		
		
		/* Tabs steuern */
		$('#ab_main').tabs(
			{
				'select': function(event, ui) {
					$('#ab_tab_index').val(ui.index);
				},
				'selected': parseInt($('#ab_tab_index').val())
			}
		);
		
		/* Alert ausblenden */
		if ( typeof $.fn.delay === 'function' ) {
			$('#setting-error-settings_updated').delay(5000).fadeOut();
		}
	}
);