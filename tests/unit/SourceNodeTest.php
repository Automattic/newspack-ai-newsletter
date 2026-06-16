<?php
declare(strict_types=1);

namespace Newspack_AI_Newsletter\Tests;

use Newspack_AI_Newsletter\Source_Node;
use Newspack_Nodes\Message;
use Newspack_Nodes\Tests\Capture_Sink_Node;
use Newspack_Nodes\Tests\TestCase;

final class SourceNodeTest extends TestCase {

	private function tick(): array {
		$request                  = Message::new_message();
		$request[ Message::TYPE ] = Message::TM_REQUEST;
		$request[ Message::KEY ]  = 'TICK';
		return $request;
	}

	public function test_tick_emits_each_fetched_item_as_struct(): void {
		$sink = new Capture_Sink_Node();
		$node = new Fake_Source_Node();
		$node->items = [
			[ 'source' => 'fake', 'id' => 'fake:1', 'title' => 'A' ],
			[ 'source' => 'fake', 'id' => 'fake:2', 'title' => 'B' ],
		];
		$node->sink( $sink );

		$req = $this->tick();
		$node->fill( $req );

		$this->assertCount( 2, $sink->captured );
		$this->assertTrue( (bool) ( $sink->captured[0][ Message::TYPE ] & Message::TM_STRUCT ) );
		$this->assertSame( 'fake:1', $sink->captured[0][ Message::VALUE ]['id'] );
	}

	public function test_dedups_by_id_across_ticks(): void {
		$sink = new Capture_Sink_Node();
		$node = new Fake_Source_Node();
		$node->items = [ [ 'source' => 'fake', 'id' => 'fake:1', 'title' => 'A' ] ];
		$node->sink( $sink );

		$a = $this->tick();
		$node->fill( $a );
		$b = $this->tick();
		$node->fill( $b );

		$this->assertCount( 1, $sink->captured, 'a seen id must not be re-emitted on a later tick' );
	}

	public function test_skips_items_with_no_id(): void {
		$sink = new Capture_Sink_Node();
		$node = new Fake_Source_Node();
		$node->items = [ [ 'source' => 'fake', 'title' => 'no id' ] ];
		$node->sink( $sink );

		$req = $this->tick();
		$node->fill( $req );

		$this->assertCount( 0, $sink->captured );
	}

	public function test_non_request_message_is_ignored(): void {
		$sink = new Capture_Sink_Node();
		$node = new Fake_Source_Node();
		$node->items = [ [ 'source' => 'fake', 'id' => 'fake:1' ] ];
		$node->sink( $sink );

		$data                  = Message::new_message();
		$data[ Message::TYPE ] = Message::TM_STRUCT;
		$node->fill( $data );

		$this->assertCount( 0, $sink->captured );
	}

}

/**
 * Concrete Source_Node whose fetch() returns canned items, so the base's
 * fill()/dedup/emit/snapshot behavior can be tested without HTTP.
 */
class Fake_Source_Node extends Source_Node {

	/** @var array<int,array<string,mixed>> */
	public array $items = [];

	protected function config(): array {
		return [];
	}

	public function fetch( array $config ): array {
		return $this->items;
	}
}
