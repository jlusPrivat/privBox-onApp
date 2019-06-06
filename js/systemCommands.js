$(document).ready(function () {
	
	// Assign for the checkboxes
	$(".checkboxNewAction").on("click", function () {
		var selects = $(this).closest("div").find("select");
		if ($(this).prop("checked")) {
			selects.prop("disabled", false);
		}
		else {
			selects.prop("disabled", true);
			selects.prop("selectedIndex", 0);
		}
	});
	
	
	$(".actLoeschen").on("click", function () {
		if ($(this).prop("readonly"))
			$(this).prop("checked", !$(this).prop("checked"));
	});
	
	
	// Assign for the delete Radio of a whole command:
	$(".comLoeschen").on("click", function () {
		var deleteButtons = $(this).closest("div").parent().find(".actLoeschen");
		// Prepare to delete everything:
		if ($(this).val() == 1) {
			if (confirm("Soll der Befehl mit allen zugehörigen Aktionen gelöscht werden?"))
				deleteButtons.prop("checked", true).prop("readonly", true);
			else
				$(this).siblings(".comLoeschen[value='0']").prop("checked", true);
		}
		else
			deleteButtons.prop("checked", false).prop("readonly", false);
	});
});