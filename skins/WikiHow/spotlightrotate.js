//Global
var currentSA = 0;
var SAinterval = 0;
var rotate = true;
var controlsflag = 0;

function rotateSA() {
	var sa0 = document.getElementById("sa0");
	var sa1 = document.getElementById("sa1");
	var sa2 = document.getElementById("sa2");

	if (controlsflag == 0) {
		showControls();
		controlsflag = 1;
	}

	if ((sa1 == null) || (sa2 == null) || (sa0 == null)) {

		//alert("Clearing Interval");
		clearInterval( SAinterval );

	} else {

		if (currentSA == 0) {
			if (!(sa1.innerHTML.match(/{{{.}}}/))) {
				Effect.Fade('sa0', { duration: 1.0, from: 1, to: 0.2 });
				Effect.Appear('sa1', { duration: 1, from: 0, to: 1 });
				document.getElementById("sa0").style.display = "none"
				document.getElementById("sa1").style.display = "block"
				document.getElementById("sa2").style.display = "none"

				currentSA = 1;
			} else {
				//alert("Clearing Interval 0");
				clearInterval( SAinterval );
			}
		} else if (currentSA == 1) {
			if (!(sa2.innerHTML.match(/{{{.}}}/))) {
				Effect.Fade('sa1', { duration: 1.0, from: 1, to: 0.2 });
				Effect.Appear('sa2', { duration: 1, from: 0, to: 1 });
				document.getElementById("sa0").style.display = "none"
				document.getElementById("sa1").style.display = "none"
				document.getElementById("sa2").style.display = "block"

				currentSA = 2;
			} else {
				//alert("Clearing Interval 1");
				clearInterval( SAinterval );
			}
		} else {
			if (document.getElementById("sa0").style.display != "block") {
				Effect.Fade('sa2', { duration: 1.0, from: 1, to: 0.2 });
				Effect.Appear('sa0', { duration: 1, from: 0, to: 1 });
				document.getElementById("sa0").style.display = "block"
				document.getElementById("sa1").style.display = "none"
				document.getElementById("sa2").style.display = "none"
			}
			currentSA = 0;
		}
	}

}

function nextSA() {
	rotateSA();
}

function backSA() {
	var sa0 = document.getElementById("sa0");
	var sa1 = document.getElementById("sa1");
	var sa2 = document.getElementById("sa2");

	if ((sa1 == null) || (sa2 == null) || (sa0 == null)) {

		//alert("Clearing Interval");
		clearInterval( SAinterval );

	} else {

		if (currentSA == 0) {
			if (!(sa2.innerHTML.match(/{{{.}}}/))) {
				Effect.Fade('sa0', { duration: 1.0, from: 1, to: 0.2 });
				Effect.Appear('sa2', { duration: 1, from: 0, to: 1 });
				document.getElementById("sa0").style.display = "none"
				document.getElementById("sa1").style.display = "none"
				document.getElementById("sa2").style.display = "block"

				currentSA = 2;
			} else {
				//alert("Clearing Interval 0");
				clearInterval( SAinterval );
			}
		} else if (currentSA == 1) {
			if (!(sa0.innerHTML.match(/{{{.}}}/))) {
				Effect.Fade('sa1', { duration: 1.0, from: 1, to: 0.2 });
				Effect.Appear('sa0', { duration: 1, from: 0, to: 1 });
				document.getElementById("sa0").style.display = "block"
				document.getElementById("sa1").style.display = "none"
				document.getElementById("sa2").style.display = "none"

				currentSA = 0;
			} else {
				//alert("Clearing Interval 1");
				clearInterval( SAinterval );
			}
		} else {
			if (document.getElementById("sa1").style.display != "block") {
				Effect.Fade('sa2', { duration: 1.0, from: 1, to: 0.2 });
				Effect.Appear('sa1', { duration: 1, from: 0, to: 1 });
				document.getElementById("sa0").style.display = "none"
				document.getElementById("sa1").style.display = "block"
				document.getElementById("sa2").style.display = "none"
			}
			currentSA = 1;
		}
	}
}

function pauseSA() {
	if (rotate == true) {
		document.getElementById('spotlight_playpause').src = "/skins/WikiHow/spotlight_play.png";
		clearInterval(SAinterval);
		rotate = false;
	} else {
		rotateSA();
		document.getElementById('spotlight_playpause').src = "/skins/WikiHow/spotlight_pause.png";
		SAinterval = setInterval('rotateSA()', 10000);
		rotate = true;
	}
}

function initSA() {
	SAinterval = setInterval('rotateSA()', 10000);
	if (document.getElementById("spotlight_controls") != null) {
		showControls();
		controlsflag = 1;
	} else {
		SAshowcontrols = setTimeout('showControls()',3000);
	}
	
}

function showControls() {
	var controls = '<img alt="scroll back" src="/skins/WikiHow/spotlight_rwd.png" height="24" width="24" onclick="return backSA();">';
	controls += '<img id="spotlight_playpause" alt="start/stop rotation" src="/skins/WikiHow/spotlight_pause.png" height="24" width="24" onclick="return pauseSA();">';
	controls += '<img alt="scroll forward" src="/skins/WikiHow/spotlight_fwd.png" height="24" width="24" onclick="return nextSA();"> ';

	if (document.getElementById("spotlight_controls") != null) {
		document.getElementById("spotlight_controls").innerHTML = controls;
	}
}

