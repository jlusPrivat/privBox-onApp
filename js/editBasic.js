function togglePwd() {
	var prop = $('input[name=pwd]').prop("disabled");
	if (prop)
		$('input[name=pwd]').prop("disabled", false);
	else
		$('input[name=pwd]').prop("disabled", true);
}

function sureDelete() {
	var item = $('input[name="delete"]');
	if (item.prop("checked") == true) {
		if (!confirm("Soll Benutzer wirklich entfernt werden?")) {
			item.prop("checked", false);
		}
	}
}