// this will print MediaWiki debug log messages to the debug console
var WH = WH || {};

WH.consoleDebug = (function () {

	// lots of this code was adapted from mediawiki.debug.js
	return  function(data) {
		if (!data || !data.log || data.log.length == 0) {
			return;
		}

		if ($("table#mw-debug-console").length == 0) {
			info = mw.config.get('debugInfo');
			if (debugInfo) {
				info.log = info.log.concat(data.log);
			}
		} else {

			var entryTypeText;

			entryTypeText = function( entryType ) {
				switch ( entryType ) {
					case 'log':
						return 'Log';
					case 'warn':
						return 'Warning';
					case 'deprecated':
						return 'Deprecated';
					default:
						return 'Unknown';
				}
			};
			for (x in data.log) {
				entry = data.log[x];
				entry.typeText = entryTypeText( entry.type );

				$('<tr>' )
						.append( $( '<td>' )
							.text( entry.typeText )
							.addClass( 'mw-debug-console-' + entry.type )
						)
						.append( $( '<td>' ).html( entry.msg ) )
						.append( $( '<td>' ).text( entry.caller ) )
						.appendTo( $('table#mw-debug-console') );
			}

			rows = $('table#mw-debug-console tr').length;
			$("#mw-debug-console a.mw-debug-panelabel").html("Console (" + rows + ")");
		}

		delete data.log;
	}
}());


