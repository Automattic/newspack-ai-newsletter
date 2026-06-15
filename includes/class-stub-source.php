<?php
/**
 * Stub_Source_Node: one canned source. Emits a fixed batch of normalized items on
 * `tick` until the real connectors land. Stands in for the live GitHub/Linear/feed
 * sources so the spine runs end-to-end immediately.
 *
 * @package Newspack_AI_Newsletter
 */

namespace Newspack_AI_Newsletter;

use Newspack_Nodes\Node;
use Newspack_Nodes\Message;
use Newspack_Nodes\Core;
use Newspack_Nodes\Command_Interpreter_Node;

\defined( 'ABSPATH' ) || exit;

class Stub_Source_Node extends Node {
	use \Newspack_Nodes\Schema_Reflection;

	/** Wire the sibling {name}:config interpreter so the `tick` verb is dispatchable. */
	public function __construct() {
		parent::__construct();
		$this->auto_wire_interpreter();
	}

	/**
	 * The ONE seam a real source replaces: return normalized ingest items. Stub = canned.
	 * Shape per the item contract: {source,id,title,url,body,timestamp}.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function items(): array {
		// Substrate clock when pumped; epoch seconds otherwise (request scope, tests).
		$now = Core::$now > 0.0 ? (int) Core::$now : \time();
		return [
			[ 'source' => 'stub', 'id' => 'stub:1', 'title' => 'Roundup Block ships', 'url' => 'https://example.test/s1', 'body' => 'AI summarizes selected posts into a draft.', 'timestamp' => $now ],
			[ 'source' => 'stub', 'id' => 'stub:2', 'title' => 'Editorial Assistant GA', 'url' => 'https://example.test/s2', 'body' => 'Inline AI assistance in the editor.', 'timestamp' => $now ],
			[ 'source' => 'stub', 'id' => 'stub:3', 'title' => 'Reader forum hits 10k members', 'url' => 'https://example.test/s3', 'body' => 'The publisher community forum crossed ten thousand members this week.', 'timestamp' => $now ],
		];
	}

	/** `tick` handler: emit each item as a TM_STRUCT message. */
	public function cmd_tick(): string {
		$count = 0;
		foreach ( $this->items() as $item ) {
			$msg                   = Message::new_message();
			$msg[ Message::TYPE ]  = Message::TM_STRUCT;
			$msg[ Message::FROM ]  = $this->name;
			$msg[ Message::VALUE ] = $item;
			// parent::fill stamps TO from a connect_node-set target, then forwards to sink.
			parent::fill( $msg );
			++$count;
		}
		return "emitted $count item(s)";
	}

	public static function node_schema(): array {
		return \array_merge( parent::node_schema(), [
			'category'     => 'Source',
			'description'  => 'Emits a canned batch of normalized items on tick (stand-in for live sources).',
			'arguments'    => [],
			'commands'     => [
				[
					'name'        => 'tick',
					'description' => 'Emit the current batch of items.',
					'args'        => [],
					// Dispatched via the {node}:config interpreter (auto_wire_interpreter() in __construct).
					'handler'     => static function ( Command_Interpreter_Node $interpreter, string $args ): string {
						/** @var self $patron */
						$patron = $interpreter->patron();
						return $patron->cmd_tick();
					},
				],
			],
			'accepts_fill' => false,
			'has_target'   => true,
		] );
	}
}
