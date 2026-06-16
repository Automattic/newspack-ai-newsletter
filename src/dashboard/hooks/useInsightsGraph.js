/**
 * useInsightsGraph — mounts the Publisher Insights dashboard graph clipped onto
 * the canonical rule-#2 backbone (`_command_interpreter → _router`) via the
 * substrate's `_http` I/O boundary, plus the application's view-model node:
 *
 *   _http          (HttpOut — POST /command boundary; .client = CommandClient)
 *   insights:view  (the view-model node React reads + pending-Promise registry)
 *
 * EVERY node sinks into the interpreter; the router routes by TO. The `insights`
 * Service_CI verb returns the FULLY-SHAPED model (sources, top, accumulated, and
 * the rendered `digest`) synchronously in the POST body, so there is NO transform
 * node and NO SSE — the page-visibility-gated poll IS the live data. The shared
 * `useDashboardGraph` owns the exospine mount, the `_http` boundary, the immediate
 * + interval poll, and the page-visibility gate; this hook supplies its view node
 * + poll command.
 *
 * The command boundary is injectable: tests pass `opts.commandClient` assigned
 * to `_http.client` so the hook never touches the network. Production lazily
 * defaults to the shared CommandClient over window.NewspackNodesData.
 *
 * Beyond the poll, the hook exposes `generate()` — an AWAITED `generate` verb the
 * "Generate digest" button calls to recompose a fresh digest on demand. It
 * stashes a pending Promise in the view's reply registry keyed by message[ID],
 * fires the command, and resolves to the digest markdown when the reply pivots back.
 */

import { useCallback, useRef } from '@wordpress/element';
import {
	newMessage,
	TYPE,
	FROM,
	TO,
	ID,
	VALUE,
	TM_COMMAND,
} from '@newspack-nodes/runtime';
import {
	useDashboardGraph,
	makeOpId,
} from '@newspack-nodes/shared/hooks/useDashboardGraph';
import '../nodes/register';

const HTTP = '_http';
const VIEW = 'insights:view';

/**
 * Build a TM_COMMAND for one of the view's verbs: TO=`_http/insights` so the
 * router peels `_http` and HttpOut POSTs the bare command to the `insights`
 * server node (this plugin's CI mount); FROM=`insights:view` is the reply pivot
 * (the CI replies TO=FROM, landing at the view).
 *
 * @param {string} verb The CI verb (`insights` poll, or `generate`).
 * @param {string} id   Correlator stamped into message[ID].
 * @return {Array} A 7-field positional Message.
 */
function buildCommand( verb, id ) {
	const m = newMessage();
	m[ TYPE ] = TM_COMMAND;
	m[ FROM ] = VIEW;
	m[ TO ] = `${ HTTP }/insights`;
	m[ ID ] = id;
	m[ VALUE ] = { name: verb, arguments: '' };
	return m;
}

/**
 * Parse a `generate` reply payload (`{"digest":"…"}` JSON string) to its markdown.
 *
 * @param {*} payload The reply's VALUE.payload.
 * @return {string} The digest markdown, or '' when absent/unparseable.
 */
function parseDigest( payload ) {
	if ( 'string' !== typeof payload ) {
		return '';
	}
	try {
		const parsed = JSON.parse( payload );
		return parsed && 'string' === typeof parsed.digest ? parsed.digest : '';
	} catch ( e ) {
		return '';
	}
}

/**
 * @param {Object} [opts]               Options (test seams).
 * @param {Object} [opts.commandClient] CommandClient seam assigned to `_http.client`.
 * @param {number} [opts.refreshMs]     Poll interval in ms (default 4000).
 * @return {{ generate: () => Promise<string> }} `generate()` recomposes the digest on demand.
 */
export function useInsightsGraph( opts = {} ) {
	const { commandClient, refreshMs = 4000 } = opts;
	const viewRef = useRef( null );

	const { interpreterRef } = useDashboardGraph( {
		mountNodes: ( interpreter ) => {
			viewRef.current = interpreter.makeNode( 'InsightsView', VIEW );
		},
		poll: ( interpreter ) =>
			interpreter.fill(
				buildCommand( 'insights', makeOpId( 'insights-op' ) )
			),
		refreshMs,
		commandClient,
	} );

	// Awaited `generate`: stash a pending Promise under the command ID, fire it,
	// and resolve to the recomposed digest markdown when the reply pivots back.
	const generate = useCallback( () => {
		const interpreter = interpreterRef.current;
		const view = viewRef.current;
		if ( ! interpreter || ! view ) {
			return Promise.reject( new Error( 'insights graph not ready' ) );
		}
		const id = makeOpId( 'insights-gen' );
		return new Promise( ( resolve, reject ) => {
			view.replies.add( id, resolve, reject );
			interpreter.fill( buildCommand( 'generate', id ) );
		} ).then( parseDigest );
	}, [ interpreterRef ] );

	return { generate };
}
