	<form method="post" action="">
		Keywords: <input type="text" name="keywords" />
		<br />
		<br />
		<fieldset name="twitter" style="padding:15px">
			<legend>Twitter Options</legend>
			Number of Results: <select name="numResults">
				<option value="10">10</option>
				<option value="20">20</option>
				<option value="30">30</option>
				<option value="40">40</option>
			</select>
		</fieldset>
		<br />
		<fieldset name="inbox q" style="padding:15px">
			<legend>Inbox Q Options</legend>
			InboxQ Type:<select name="inboxType">
				<option value="unfiltered">Unfiltered</option>
				<option value="filtered">Filtered</option>
				<option value="unicorn">Unicorn</option>
				<option value="quip">QUIP</option>
				<option value="qualityInboxQ">Quality Inbox Q</option>
			</select>

		</fieldset>
		<br />
		<input type="button" name="streamSubmit" value="Search" />

	</form>

	<table style="width:100%;border:1px solid black">
		<thead>
			<tr>
				<th>InboxQ</th>
				<th>Direct Twitter</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td id="streamLeft" style="vertical-align:top;width:50%"></td>
				<td id="streamRight" style="vertical-align:top;width:50%"></td>
			</tr>
		</tbody>
	</table>
</div>