function addCard(id) {
	
	// First lock the inputs to prevent multi-triggering:
	$('input').prop("disabled", true);
	
	// Run a Countdown
	var timer = window.setInterval(function() {
		var counter = $('#countdown').html();
		counter = counter - 1;
		if (counter < 0) {
			window.clearInterval(timer);
			$('input').prop("disabled", false);
			$('#countdown').html('30');
			alert("Zeit abgelaufen");
		}
		else {
			$('#countdown').html(counter);
		}
	}, 1000);
	
	// Run the request
	$.ajax("js_funcs.php", {
	"timeout": 30000,
	"method": "post",
	"data": {"action": "addCard", "id": id},
	"success": function(data) {
		// Reset everything:
		window.clearInterval(timer);
		$('input').prop("disabled", false);
		$('#countdown').html('30');
		
		// If data is true:
		if ($.isNumeric(data)) {
			$('#cardId').html('#' + data);
		}
		else
			alert(data);
	}});
	
}

function delCard(id) {
	
	$.post("js_funcs.php", {"action": "delCard", "id": id}, function(data) {
		if (data == "OK")
			$('#cardId').html('<span style="color: #800000;">Keine Karte registriert</span>');
		else
			alert(data);
	});
	
}