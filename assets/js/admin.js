/* global tailtpData, ajaxurl */
jQuery( document ).ready( function ( $ ) {
	'use strict';

	var s     = tailtpData.strings;
	var total = tailtpData.total;

	// ── Tab switching ─────────────────────────────────────────
	function activateTab( tabId ) {
		$( '.taitp-nav-tab' ).removeClass( 'taitp-tab-active' );
		$( '.taitp-tab-panel' ).removeClass( 'taitp-tab-active' );
		$( '#taitp-tab-' + tabId ).addClass( 'taitp-tab-active' );
		$( '#taitp-panel-' + tabId ).addClass( 'taitp-tab-active' );
		try { localStorage.setItem( 'taitp_active_tab', tabId ); } catch ( e ) {}
	}

	$( '.taitp-nav-tab' ).on( 'click', function ( e ) {
		e.preventDefault();
		var tabId = $( this ).attr( 'id' ).replace( 'taitp-tab-', '' );
		activateTab( tabId );
		this.blur();
	} );

	// Restore last active tab
	try {
		var saved = localStorage.getItem( 'taitp_active_tab' );
		if ( saved ) activateTab( saved );
	} catch ( e ) {}

	var isRunning    = false;
	var failedStrings = [];

	// ── Track skipped strings ─────────────────────────────────
	function trackFailed( item ) {
		if ( item && item.log && item.log.includes( 'SKIPPED' ) ) {
			failedStrings.push( {
				id:          item.id          || '',
				original:    item.original    || '',
				translation: item.translation || '',
				reason:      item.judge_feedback || ''
			} );
			$( '#taitp-failed-count' ).text( failedStrings.length + ' ' + s.skipped );
			$( '#taitp-download-failed-btn' ).prop( 'disabled', false );
		}
	}

	// ── Download skipped report ───────────────────────────────
	$( '#taitp-download-failed-btn' ).on( 'click', function () {
		if ( ! failedStrings.length ) return;

		var date       = new Date().toLocaleDateString();
		var table      = tailtpData.selectedTable;
		var sourceLang = tailtpData.sourceLang;
		var targetLang = tailtpData.targetLang;

		function esc( str ) {
			return String( str )
				.replace( /&/g, '&amp;' )
				.replace( /</g, '&lt;' )
				.replace( />/g, '&gt;' )
				.replace( /"/g, '&quot;' );
		}

		var cards = failedStrings.map( function ( r ) {
			return (
				'<div class="card">' +
					'<div class="card-header">' +
						'<strong>ID ' + esc( r.id ) + '</strong>' +
						'<span class="badge">&#10060; ' + esc( s.skippedLabel ) + '</span>' +
					'</div>' +
					'<div class="translation-box">' +
						'<div class="label">&#128221; ' + esc( s.original ) + '</div>' +
						'<div class="text original">' + esc( r.original ) + '</div>' +
						'<div class="label" style="margin-top:8px;">&#129302; ' + esc( s.lastAttempted ) + '</div>' +
						'<div class="text">' + esc( r.translation ) + '</div>' +
					'</div>' +
					( r.reason ? '<div class="reason"><strong>&#9878;&#65039; ' + esc( s.reason ) + '</strong> ' + esc( r.reason ) + '</div>' : '' ) +
				'</div>'
			);
		} ).join( '' );

		var html = '<!DOCTYPE html>\n' +
			'<html lang="en">\n<head>\n' +
			'<meta charset="UTF-8">\n' +
			'<meta name="viewport" content="width=device-width, initial-scale=1.0">\n' +
			'<title>' + esc( s.skippedReport ) + ' \u2014 ' + date + '</title>\n' +
			'<style>\n' +
			'* { box-sizing: border-box; margin: 0; padding: 0; }\n' +
			'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f0f0f1; color: #1d2327; padding: 30px 20px; }\n' +
			'.wrap { max-width: 860px; margin: 0 auto; }\n' +
			'.plugin-header { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #dcdcde; }\n' +
			'.plugin-header h1 { font-size: 22px; font-weight: 700; color: #1d2327; }\n' +
			'.plugin-version { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 11px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; background: #eef6fd; color: #0c4a8a; border: 1px solid #bee0f7; }\n' +
			'.bmc-btn { display: inline-flex; align-items: center; gap: 7px; margin-left: auto; padding: 5px 14px; background: #FFDD00; color: #000; font-weight: 700; font-size: 13px; border-radius: 6px; text-decoration: none; }\n' +
			'.report-title { font-size: 16px; font-weight: 600; color: #1d2327; margin-bottom: 4px; }\n' +
			'.meta { font-size: 13px; color: #646970; margin-bottom: 20px; }\n' +
			'.meta code { background: #e0e0e0; padding: 1px 6px; border-radius: 3px; font-size: 12px; }\n' +
			'.card { background: #fff; border: 1px solid #dcdcde; border-left: 4px solid #c02b2b; border-radius: 4px; padding: 14px; margin-bottom: 14px; }\n' +
			'.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }\n' +
			'.card-header strong { font-size: 13px; color: #3c434a; }\n' +
			'.badge { background: #fdf2f2; color: #9b1c1c; border: 1px solid #f5c6c6; font-size: 11px; font-weight: 600; padding: 2px 10px; border-radius: 11px; text-transform: uppercase; letter-spacing: .4px; }\n' +
			'.translation-box { background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 3px; padding: 10px 12px; margin-bottom: 8px; }\n' +
			'.label { font-size: 10px; color: #646970; text-transform: uppercase; font-weight: 600; letter-spacing: .5px; margin-bottom: 4px; }\n' +
			'.text { font-size: 13px; color: #1d2327; line-height: 1.5; }\n' +
			'.text.original { color: #646970; }\n' +
			'.reason { font-size: 11px; color: #50575e; border-top: 1px solid #f0f0f1; padding-top: 8px; line-height: 1.5; }\n' +
			'.summary { background: #fff; border: 1px solid #dcdcde; border-radius: 4px; padding: 12px 16px; margin-bottom: 20px; font-size: 13px; display: flex; gap: 20px; }\n' +
			'.summary span { color: #646970; }\n' +
			'.summary strong { color: #1d2327; }\n' +
			'</style>\n</head>\n<body>\n' +
			'<div class="wrap">\n' +
			'  <div class="plugin-header">\n' +
			'    <h1>&#127760; ' + esc( s.pluginName ) + '</h1>\n' +
			'    <span class="plugin-version">v' + esc( tailtpData.version ) + '</span>\n' +
			'    <a href="https://buymeacoffee.com/vage91" target="_blank" class="bmc-btn">&#9749; Buy me a coffee</a>\n' +
			'  </div>\n' +
			'  <p class="report-title">' + esc( s.skippedReport ) + '</p>\n' +
			'  <p class="meta">' + esc( s.generatedOn ) + ' ' + date + ' &nbsp;&middot;&nbsp; <code>' + esc( table ) + '</code> &nbsp;&middot;&nbsp; ' + esc( sourceLang ) + ' \u2192 ' + esc( targetLang ) + '</p>\n' +
			'  <div class="summary">\n' +
			'    <div><span>' + esc( s.totalSkipped ) + '</span> <strong>' + failedStrings.length + '</strong></div>\n' +
			'  </div>\n' +
			cards + '\n' +
			'</div>\n</body>\n</html>';

		var blob = new Blob( [ html ], { type: 'text/html;charset=utf-8;' } );
		var url  = URL.createObjectURL( blob );
		var a    = document.createElement( 'a' );
		a.href     = url;
		a.download = 'skipped_strings_' + new Date().toISOString().slice( 0, 10 ) + '.html';
		a.click();
		URL.revokeObjectURL( url );
	} );

	// ── Auto-fill languages from selected table ───────────────
	function syncLangsFromTable( tableKey ) {
		if ( ! tableKey || typeof tailtpData.tableLangMap === 'undefined' ) return;
		var langs = tailtpData.tableLangMap[ tableKey ];
		if ( ! langs ) return;
		$( '#taitp_source_lang' ).val( langs.source );
		$( '#taitp_target_lang' ).val( langs.target );
		$( '#taitp-lang-notice-text' ).text( langs.source + ' \u2192 ' + langs.target );
		$( '#taitp-lang-notice' ).css( 'display', 'flex' );
	}

	$( '#taitp_selected_table' ).on( 'change', function () { syncLangsFromTable( $( this ).val() ); } );
	syncLangsFromTable( $( '#taitp_selected_table' ).val() );

	// ── Mode visibility + description ────────────────────────
	var modeDescriptions = {
		translator_only: s.modeDescTranslatorOnly,
		full:            s.modeDescFull
	};

	function updateModeDesc( val ) {
		$( '#taitp-mode-description' ).text( modeDescriptions[ val ] || '' );
	}

	function syncModeVisibility( mode ) {
		var judgeOnly = mode === 'translator_only';
		$( '#taitp-judge-model-row' ).toggle( ! judgeOnly );
		$( '#taitp-judge-section' ).toggle( ! judgeOnly );
	}

	$( '#taitp_mode' ).on( 'change', function () {
		syncModeVisibility( $( this ).val() );
		updateModeDesc( $( this ).val() );
	} );
	syncModeVisibility( $( '#taitp_mode' ).val() );
	updateModeDesc( $( '#taitp_mode' ).val() );

	// ── Judge endpoint toggle ─────────────────────────────────
	$( '#taitp-judge-endpoint-toggle' ).on( 'change', function () {
		$( '#taitp-judge-endpoint-wrap' ).toggle( this.checked );
	} );

	// ── Batch toggle ──────────────────────────────────────────
	$( '#taitp-batch-toggle' ).on( 'change', function () {
		var on = this.checked;
		$( '#taitp-batch-size-wrap, #taitp-batch-delay-wrap' ).css( 'opacity', on ? '1' : '.45' );
	} );

	// ── Connection test ───────────────────────────────────────
	$( '#taitp-test-btn' ).on( 'click', function () {
		var $btn  = $( this );
		var $res  = $( '#taitp-test-result' );
		var judgeEnabled = $( '#taitp-judge-endpoint-toggle' ).is( ':checked' );

		$btn.prop( 'disabled', true );
		$res.html( '<span class="spinner is-active" style="float:none;vertical-align:middle;"></span> Testing\u2026' ).css( 'color', '#646970' );

		$.post( ajaxurl, {
			action:   'taitp_test_connection',
			security: tailtpData.nonce,
			model:    $( '#taitp_main_model' ).val()
		}, function ( r ) {
			if ( ! r.success ) {
				$res.html( '\u274C ' + s.translatorConnError ).css( 'color', '#c02b2b' );
				$btn.prop( 'disabled', false );
				return;
			}
			if ( ! judgeEnabled ) {
				$res.html( '\u2705 ' + s.connectionOk ).css( 'color', '#1a7a2e' );
				$btn.prop( 'disabled', false );
				return;
			}
			$res.html( '<span class="spinner is-active" style="float:none;vertical-align:middle;"></span> ' + s.testingJudge ).css( 'color', '#646970' );
			$.post( ajaxurl, {
				action:   'taitp_test_judge_connection',
				security: tailtpData.nonce,
				model:    $( '#taitp_judge_model' ).val()
			}, function ( rj ) {
				rj.success
					? $res.html( '\u2705 ' + s.translatorOk + ' &nbsp;&middot;&nbsp; \u2705 ' + s.judgeOk ).css( 'color', '#1a7a2e' )
					: $res.html( '\u2705 ' + s.translatorOk + ' &nbsp;&middot;&nbsp; \u274C ' + s.judgeConnError ).css( 'color', '#c02b2b' );
			} ).fail( function () {
				$res.html( '\u2705 ' + s.translatorOk + ' &nbsp;&middot;&nbsp; \u274C ' + s.judgeReqFailed ).css( 'color', '#c02b2b' );
			} ).always( function () { $btn.prop( 'disabled', false ); } );
		} ).fail( function () {
			$res.html( '\u274C ' + s.translatorReqFailed ).css( 'color', '#c02b2b' );
			$btn.prop( 'disabled', false );
		} );
	} );

	// ── Start / Stop ──────────────────────────────────────────
	$( '#taitp-start-btn' ).on( 'click', function () {
		isRunning = true;
		$( this ).prop( 'disabled', true );
		$( '#taitp-stop-btn' ).prop( 'disabled', false );
		$( '#taitp-console' ).empty();
		appendLog( 'info', '\u25B6 ' + s.processingStarted );
		processNext();
	} );

	$( '#taitp-stop-btn' ).on( 'click', function () {
		isRunning = false;
		$( this ).prop( 'disabled', true );
		$( '#taitp-start-btn' ).prop( 'disabled', false );
		appendLog( 'warn', '\u25A0 ' + s.processingStopped );
	} );

	// ── Main translation loop ─────────────────────────────────
	function processNext() {
		if ( ! isRunning ) return;

		$.ajax( {
			url:  ajaxurl,
			type: 'POST',
			data: { action: 'taitp_translate_step', security: tailtpData.nonce },
			success: function ( response ) {
				if ( ! response.success || ! response.data ) {
					appendLog( 'warn', '\u26A0 ' + s.invalidResponse );
					setTimeout( processNext, 3000 );
					return;
				}

				if ( response.data.finished ) {
					appendLog( 'ok', '\u2705 ' + s.translationCompleted );
					$( '#taitp-stop-btn' ).trigger( 'click' );
					return;
				}

				var results = Array.isArray( response.data ) ? response.data : [ response.data ];

				results.forEach( function ( item ) {
					if ( item.log ) {
						var cls = item.log.includes( 'green' )    ? 'ok'
								: item.log.includes( 'red' )      ? 'error'
								: item.log.includes( 'orange' )   ? 'warn'
								: item.log.includes( '#00a0d2' )  ? 'auto'
								: 'info';
						var text = $( '<div>' ).html( item.log ).text();
						appendLog( cls, text );
					}
					trackFailed( item );
					renderCard( item );
				} );

				var last = results[ results.length - 1 ];
				updateProgress( last.done_count, last.counts );
				setTimeout( processNext, 80 );
			},
			error: function () {
				appendLog( 'error', '\u2716 ' + s.networkError );
				setTimeout( processNext, 5000 );
			}
		} );
	}

	// ── Helpers ───────────────────────────────────────────────
	function appendLog( cls, text ) {
		var $c = $( '#taitp-console' );
		var ts = new Date().toLocaleTimeString( 'en-GB', { hour12: false } );
		$c.append( '<div class="log-' + cls + '">[' + ts + '] ' + text + '</div>' );
		$c.scrollTop( $c[ 0 ].scrollHeight );
	}

	function updateProgress( doneCount, counts ) {
		$( '#taitp-counter' ).text( doneCount );
		var perc = total > 0 ? Math.round( ( doneCount / total ) * 100 ) : 0;
		$( '#taitp-percent' ).text( perc + '%' );
		$( '#taitp-progress-bar' ).css( 'width', perc + '%' );
		if ( counts ) {
			$( '#taitp-stat-done' ).text( counts.done );
			$( '#taitp-stat-skipped' ).text( counts.skipped );
			$( '#taitp-stat-pending' ).text( counts.pending );
		}
	}

	function renderCard( item ) {
		if ( ! item || item.id === undefined || item.id === null ) return;

		var itemId   = String( item.id );
		var approved = !! item.approved;
		var badgeCls = approved ? 'taitp-badge taitp-badge-green' : 'taitp-badge taitp-badge-red';
		var label    = approved ? '\u2705 ' + s.approved : '\u274C ' + s.rejected;
		var cardCls  = approved ? 'taitp-judge-card approved' : 'taitp-judge-card rejected';

		var $box = $( '#taitp-reject-box' );
		if ( $box.find( 'p' ).length ) $box.empty();

		var $card = $( '<div>' ).addClass( cardCls ).attr( 'data-taitp-id', itemId );

		var $header = $( '<div>' ).addClass( 'taitp-judge-card-header' )
			.append( $( '<strong>' ).text( 'ID ' + itemId ) )
			.append( $( '<span>' ).addClass( badgeCls ).text( label ) );

		var $transBox = $( '<div>' ).addClass( 'taitp-translation-box' )
			.append( $( '<div>' ).addClass( 'taitp-label' ).text( '\uD83D\uDCDD ' + s.original ) )
			.append( $( '<div>' ).addClass( 'taitp-text' ).css( 'color', '#646970' ).text( item.original || '' ) )
			.append( $( '<div>' ).addClass( 'taitp-label' ).css( 'margin-top', '6px' ).text( '\uD83E\uDD16 ' + s.translation ) )
			.append( $( '<div>' ).addClass( 'taitp-text' ).text( item.translation ) );

		if ( item.rationale && item.rationale !== 'N/A' ) {
			$transBox.append(
				$( '<div>' ).addClass( 'taitp-rationale' ).html( '<strong>' + s.reason + '</strong> ' + $( '<div>' ).text( item.rationale ).html() )
			);
		}

		$card.append( $header ).append( $transBox );

		if ( item.judge_feedback ) {
			$card.append(
				$( '<div>' ).addClass( 'taitp-judge-feedback' ).html( '<strong>\u2696\uFE0F ' + s.judge + '</strong> ' + $( '<div>' ).text( item.judge_feedback ).html() )
			);
		}

		var $existing = $box.find( '[data-taitp-id="' + itemId + '"]' );
		if ( $existing.length ) {
			$existing.first().before( $card );
			if ( approved ) {
				$existing.css( { opacity: '0.45', filter: 'grayscale(40%)' } );
			}
		} else {
			$box.prepend( $card );
		}
	}
} );
