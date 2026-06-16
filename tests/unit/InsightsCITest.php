<?php
declare(strict_types=1);

namespace Newspack_AI_Newsletter\Tests;

use Newspack_AI_Newsletter\Insights_CI_Node;
use Newspack_Nodes\Tests\TestCase;

/**
 * Insights_CI is the dashboard's server read. Beyond the scored-pipeline model
 * (sources/top/accumulated) it now surfaces the REAL rendered digest — the
 * latest `digest:log` segment — and a `generate` core that recomposes a fresh
 * digest from the snapshot items via the shared Digest_Composer.
 */
final class InsightsCITest extends TestCase {

	private string $tmp = '';

	protected function setUp(): void {
		parent::setUp();
		$this->tmp = \sys_get_temp_dir() . '/insights-ci-' . \uniqid();
		\mkdir( $this->tmp, 0777, true );
	}

	protected function tearDown(): void {
		foreach ( (array) \glob( $this->tmp . '/*' ) as $f ) {
			\unlink( $f );
		}
		if ( \is_dir( $this->tmp ) ) {
			\rmdir( $this->tmp );
		}
		parent::tearDown();
	}

	public function test_read_latest_digest_returns_newest_segment(): void {
		$path = $this->tmp . '/digest.md';
		\file_put_contents( $path . '.0', 'old digest' );
		\file_put_contents( $path . '.1', 'new digest' );
		$this->assertSame( 'new digest', Insights_CI_Node::read_latest_digest( $path ) );
	}

	public function test_read_latest_digest_missing_file_is_empty_string(): void {
		$this->assertSame( '', Insights_CI_Node::read_latest_digest( $this->tmp . '/none.md' ) );
	}

	public function test_insights_model_carries_the_digest(): void {
		$path = $this->tmp . '/digest.md';
		\file_put_contents( $path . '.0', '## Real digest' );
		// No scored offsetlogs in $this->tmp, so the pipeline model is empty, but the digest is present.
		$model = Insights_CI_Node::read_insights_model( $this->tmp, $path );
		$this->assertSame( '## Real digest', $model['digest'] );
		$this->assertSame( 0, $model['accumulated'] );
	}

	public function test_read_snapshot_items_empty_when_no_dirs(): void {
		$this->assertSame( [], Insights_CI_Node::read_snapshot_items( $this->tmp ) );
	}

	public function test_generate_json_composes_a_digest_from_items(): void {
		// No ai_proxy_token configured → Settings::llm_client() is null → ranked-list fallback.
		$json   = Insights_CI_Node::generate_json( [ [ 'summary' => 'shipped X', 'score' => 5.0 ] ] );
		$parsed = \json_decode( $json, true );
		$this->assertIsArray( $parsed );
		$this->assertStringContainsString( '- shipped X', (string) $parsed['digest'] );
	}
}
