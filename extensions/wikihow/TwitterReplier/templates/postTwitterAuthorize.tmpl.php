<script type="text/javascript">
	var screenName = "<?= $screenName ?>";
	var image = "<?= $profileImage ?>";

	var twitterHandle = window.opener.document.getElementById("twitter_handle");
	twitterHandle.innerHTML = "@" + screenName;
	twitterHandle.setAttribute("href", "http://twitter.com/" + screenName);

	var profileImage = window.opener.document.getElementById("profileImage");
	profileImage.innerHTML = image;

	var elem = window.opener.document.getElementById("reply_as");
	elem.style.display = "block";

	window.close();
</script>
