jQuery(document).ready(
	function() {
		/* Size */
		var width = jQuery('#ab_chart').parent().width()
			height = 140;

		/* Chart */
		var chart = Raphael('ab_chart', width, height);

		/* Linechart */
		chart.lineChart(
			{
				'width': width,
				'height': height,
				'data_holder': 'ab_chart_data',
				'gutter': {
					top: 20,
					left: -14,
					right: 0,
					bottom: 20
			    },
			    'colors': {
					'master': '#3399CC'
				}
			}
		);
	}
);