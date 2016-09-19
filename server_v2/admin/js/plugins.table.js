(function ($) {
	// On sprting thead id should to be eq to data index
	// Sorrting table on table head click
	$.fn.sorttable = function() {
		var table = this;
		this.find('thead:first').on('click', 'th:not(:first-child)', function() {
			var thid = $(this).prop('id');
			var dir = 1;
			if ($(this).find('span').hasClass('glyphicon')) {
				$(this).find('span').toggleClass('glyphicon-triangle-bottom glyphicon-triangle-top');
			}
			else {
				$(this).parent().find('span').removeClass();
				$(this).find('span').addClass('glyphicon glyphicon-triangle-top');
			}
			if ($(this).find('span').hasClass('glyphicon-triangle-bottom'))
				dir = -1;
			// Table sorting
			var rows = table.find('tbody:first tr').get();
			rows.sort(function(a, b) {
				var A = $(a).data(thid);
				var B = $(b).data(thid);
				if (A < B) {
					return -1*dir;
				}
				if (A > B) {
					return 1*dir;
				}
				return 0;
			});
			$.each(rows, function(index, row) {
				table.children('tbody:first').append(row);
			});
		});
		return table;
	};
	// Filter table by search input
	$.fn.filtertable = function() {
		var table = this;
		$('#search').on('keyup', function() {
			var val = $.trim($(this).val()).replace(/ +/g, ' ').toLowerCase();
			var $rows = table.find('tbody:first tr');
			$rows.show().filter(function() {
				var text = $(this).text().replace(/\s+/g, ' ').toLowerCase();
				return !~text.indexOf(val);
			}).hide();
		});
		return table;
	}

}(jQuery));