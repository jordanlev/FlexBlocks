/*** drag-n-drop sorting ***/
$(document).ready(function() {
	$('.sortable-container table tbody').sortable({
		handle: '.sortable-handle',
		axis: 'y',
		containment: '.sortable-container', //contain to a wrapper div (not the <tbody> itself), so there's room for dropping at top and bottom of list
		helper: sortableHelper, //prevent cell widths from collapsing while dragging (see http://www.foliotek.com/devblog/make-table-rows-sortable-using-jquery-ui-sortable/ )
		stop: sortableStop, //save data back to server
		cursor: 'move'
	}).disableSelection();
	
	function sortableHelper(event, ui) {
		ui.children().each(function() {
			$(this).width($(this).width());
		});
		return ui;
	}
	
	function sortableStop(event, ui) {
		var ids = [];
		ui.item.closest('table').find('tbody tr').each(function() {
			ids.push($(this).attr('data-sortable-id'));
		});
		
		var url = $('.sortable-container').attr('data-sortable-save-url');
		var token_name = $('.sortable-container').attr('data-sortable-save-token-name');
		var token_value = $('.sortable-container').attr('data-sortable-save-token-value');
		var data = {};
		data['ids'] = ids.join();
		data[token_name] = token_value;
		$.post(url, data);
	}
});