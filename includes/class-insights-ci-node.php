<?php
/**
 * Insights_CI_Node: the dashboard's server-side read. Its `insights` verb reads the
 * latest offsetlog snapshot the Consumer co-commits (the digest's save_state cache)
 * and returns a shaped model — durable, synchronous, no live-worker dependency.
 *
 * @package Newspack_AI_Newsletter
 */

namespace Newspack_AI_Newsletter;

use Newspack_Nodes\Service_CI_Node;
use Newspack_Nodes\Command_Interpreter_Node;
use Newspack_Nodes\Partition_Node;
use Newspack_Nodes\Config;

\defined( 'ABSPATH' ) || exit;

class Insights_CI_Node extends Service_CI_Node {

	private const TOP_N = 10;

	/** Coerce an untrusted (JSON-sourced) score to float; non-numeric → 0.0. */
	private static function to_float( mixed $value ): float {
		return \is_numeric( $value ) ? (float) $value : 0.0;
	}

	/** JSON model for the `insights` verb; resolves the live offsets dir + digest path. */
	public function build_insights_json(): string {
		$model = self::read_insights_model( Config::get_offsets_directory(), Settings::DIGEST_PATH );
		return (string) \wp_json_encode( $model );
	}

	/**
	 * Testable core: merge every `scored.p*` snapshot into { sources:{name:count},
	 * top:[{source,title,score}], accumulated:N } and attach `digest` — the latest
	 * rendered digest:log segment (the REAL newsletter the dashboard displays).
	 *
	 * @return array{sources: array<string,int>, top: array<int,array<string,mixed>>, accumulated: int, digest: string}
	 */
	public static function read_insights_model( string $offsets_dir, string $digest_path ): array {
		$digest = self::read_latest_digest( $digest_path );
		$items  = self::read_snapshot_items( $offsets_dir );
		if ( [] === $items ) {
			return [ 'sources' => [], 'top' => [], 'accumulated' => 0, 'digest' => $digest ];
		}

		$sources = [];
		foreach ( $items as $item ) {
			$source             = \is_string( $item['source'] ?? null ) ? $item['source'] : '?';
			$sources[ $source ] = ( $sources[ $source ] ?? 0 ) + 1;
		}

		\usort(
			$items,
			static fn ( array $a, array $b ): int => self::to_float( $b['score'] ?? null ) <=> self::to_float( $a['score'] ?? null )
		);
		$top = [];
		foreach ( \array_slice( $items, 0, self::TOP_N ) as $item ) {
			$top[] = [
				'source' => $item['source'] ?? '?',
				'title'  => $item['title'] ?? '',
				'score'  => self::to_float( $item['score'] ?? null ),
			];
		}

		return [ 'sources' => $sources, 'top' => $top, 'accumulated' => \count( $items ), 'digest' => $digest ];
	}

	/**
	 * Merge every `scored.p*` snapshot's accumulated items into one list (the full
	 * item objects, not the trimmed top) — the input both the model shaping and the
	 * `generate` recompose read.
	 *
	 * @return array<int,array<array-key,mixed>>
	 */
	public static function read_snapshot_items( string $offsets_dir ): array {
		$dirs = \glob( \rtrim( $offsets_dir, '/' ) . '/scored.p*', \GLOB_ONLYDIR );
		if ( false === $dirs || [] === $dirs ) {
			return [];
		}
		$items = [];
		foreach ( $dirs as $dir ) {
			foreach ( self::read_cache_items( $dir ) as $item ) {
				$items[] = $item;
			}
		}
		return $items;
	}

	/**
	 * The latest rendered digest: the newest `{path}.{seg}` segment the digest:log
	 * Node writes (Log lays segments out as `{file}.0`, `{file}.1`, …; segment_size=1
	 * gives one digest per segment, so the highest suffix is the most recent FLUSH).
	 * '' when nothing has been flushed yet.
	 */
	public static function read_latest_digest( string $path ): string {
		$segments = \glob( $path . '.*' );
		if ( false === $segments || [] === $segments ) {
			return '';
		}
		$newest = '';
		$best   = -1;
		foreach ( $segments as $segment ) {
			$suffix = \substr( $segment, \strlen( $path ) + 1 );
			if ( ! \ctype_digit( $suffix ) ) {
				continue;
			}
			$seg = (int) $suffix;
			if ( $seg > $best ) {
				$best   = $seg;
				$newest = $segment;
			}
		}
		if ( '' === $newest ) {
			return '';
		}
		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- a local log segment, not a remote fetch.
		$content = \file_get_contents( $newest );
		return \is_string( $content ) ? $content : '';
	}

	/**
	 * Recompose a fresh digest from the given items via the shared composer (LLM,
	 * ranked-list fallback) and return it as `{ digest: markdown }` JSON.
	 *
	 * @param array<int,array<array-key,mixed>> $items
	 */
	public static function generate_json( array $items ): string {
		$draft = Digest_Composer::compose( $items, Settings::llm_client(), Settings::get_string( 'relevance_profile' ) );
		return (string) \wp_json_encode( [ 'digest' => $draft ] );
	}

	/**
	 * Read the latest snapshot record of one offset dir and return its cache['items'].
	 * Mirrors CLI::read_offsetlog_entry — newest segment, last line, unpack VALUE.
	 *
	 * @return array<int,array<array-key,mixed>>
	 */
	private static function read_cache_items( string $offset_dir ): array {
		$value = Partition_Node::read_latest_value_at( $offset_dir );
		$cache = \is_array( $value ) && \is_array( $value['cache'] ?? null ) ? $value['cache'] : [];
		$items = $cache['items'] ?? null;
		if ( ! \is_array( $items ) ) {
			return [];
		}
		$out = [];
		foreach ( $items as $item ) {
			if ( \is_array( $item ) ) {
				$out[] = $item;
			}
		}
		return $out;
	}

	public static function node_schema(): array {
		return \array_merge( parent::node_schema(), [
			'category'    => 'Service',
			'description' => 'Reads the scored-pipeline snapshot + rendered digest; serves the dashboard insights model and recomposes on demand.',
			'commands'    => [
				[
					'name'        => 'insights',
					'description' => 'Return the current Publisher Insights model (sources, top, accumulated, digest).',
					'args'        => [],
					'handler'     => static function ( Command_Interpreter_Node $interpreter, string $args ): string {
						self::require_manage_options();
						// A Service_CI verb runs on the CI itself — the interpreter IS this node.
						/** @var self $ci */
						$ci = $interpreter;
						return $ci->build_insights_json();
					},
				],
				[
					'name'        => 'generate',
					'description' => 'Recompose a fresh digest from the current items via the LLM; returns its markdown.',
					'args'        => [],
					'handler'     => static function ( Command_Interpreter_Node $interpreter, string $args ): string {
						self::require_manage_options();
						return self::generate_json( self::read_snapshot_items( Config::get_offsets_directory() ) );
					},
				],
			],
		] );
	}
}
