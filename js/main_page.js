function triggerCommand(id) {
	$.post("js_funcs.php", {"action": "triggerCommand", "id": id}, function(data) {
		if (data != "OK")
			alert(data);
	});
}