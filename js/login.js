var user_id;
var intervall;
var longpull;

$(document).ready(function () {
	
	// Set the Click-Trigger:
	$('.user').click(function () {
		
		// Get all information:
		var position = $(this).position();
		var height = $(this).outerHeight();
		var width = $(this).outerWidth();
		var zwischen = $(this).attr("id").split("_");
		user_id = zwischen[1];
		$('#countdown').html("30");
		
		// Set the border-height and width:
		$('#border_active').height(height + 6).width(width + 6);
		
		// Clone the given element and set new propertys for it:
		$(this).clone(false).attr("id", "active")
		.css({"position": "absolute", "top": position.top, "left": position.left, "z-index": "3"})
		.attr("onclick", "unactivate();").appendTo("body");
		
		// Check, if a Pwd is required:
		if ($(this).hasClass("pwd"))
			switchDisplay('pwd', true);
		else
			switchDisplay('pwd', false);
		
		// Reset the Display for Card-auth:
		switchDisplay('keycard', true);
		
		// Fade everything in
		$('#fader').fadeTo("slow", .8);
		$('#prompt').fadeIn("slow", function() {if ($('#active').hasClass("pwd")) $('#pwdField').focus();});
		var pos_prompt = $('#border_active').offset();
		$('#active').animate({"left": pos_prompt.left - 22, "top": pos_prompt.top - 22}, "slow");
		
		// Set the countdown-interval
		intervall = setInterval(function() {
			var counter = $('#countdown').html() - 1;
			if (counter == 0)
				unactivate();
			$('#countdown').html(counter);
		}, 1000);
		
		// Start the Longpull-Connection:
		longpull = $.ajax({
			"url": "js_funcs.php",
			"timeout": 35000,
			"type": "POST",
			"dataType": "text",
			"data": {"action": "validCard", "id": user_id},
			"success": function(data) {
				if (data == "OK")
					switchDisplay('keycard', false);
			}
		});
		
	});
	
});


function unactivate() {
	
	// Fade out all 3 elements:
	$('#fader').fadeOut("fast");
	$('#prompt').fadeOut("fast");
	$('#active').fadeOut("fast", function() {
		$('#active').remove();
	});
	clearInterval(intervall);
	longpull.abort();
	$.post("js_funcs.php", {"action": "abortLogin"});
	
}


function switchDisplay(field, toggle) {
	if (field == "continue" && toggle) {
		clearInterval(intervall);
		$('#countdown').fadeOut("slow", function() {
			$('#continue').css("display", "inline-block").fadeIn("slow");
		});
		return true;
	}
	
	if (toggle) {
		// Field has to be shown (Cross icon)
		$('#' + field).css("background-image", "url(imgs/cross_mid.png)");
		if (field == "pwd") {
			$('#pwdField').prop("disabled", false);
		}
	}
	else {
		// Dont show (Check icon)
		$('#' + field).css("background-image", "url(imgs/check_mid.png)");
		if (field == "pwd") {
			$('#pwdField').prop("disabled", true);
		} 
		
		// Also check, if both have go:
		if ($('#pwd').css("background-image").match("check_mid.png")
		&& $('#keycard').css("background-image").match("check_mid.png"))
			switchDisplay("continue", true);
	}
}


function sendPwd() {
	// Get the Value and reset the field
	var pwd = $('#pwdField').val();
	$('#pwdField').val("");
	
	// Quit, if the pwd-field is empty:
	if (pwd == '') {
		alert("Sie haben keine Eingabe gemacht!");
		return false;
	}
	
	// Send the data and wait for Feedback
	$.post("js_funcs.php", {"action": "validPwd", "pwd": pwd, "id": user_id}, function(data) {
		if (data == "OK")
			switchDisplay("pwd", false);
	});
	
	return false;
}