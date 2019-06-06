function addSet(uid, id) {
	var link = 'index.php?action=user&subac=editPermissions&id='
	+ uid + '&addSet=' + id;
	window.location.href = link;
}

function delSet(uid, id) {
	var link = 'index.php?action=user&subac=editPermissions&id='
	+ uid + '&delSet=' + id;
	if (confirm("Sicher, dass Berechtigung entfernt werden soll?"))
		window.location.href = link;
}