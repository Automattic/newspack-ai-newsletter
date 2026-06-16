<?php
/**
 * Digest_Composer: the shared "items → markdown digest" core.
 *
 * Used by both the worker's Digest_Builder FLUSH (writes digest:log) and the
 * dashboard's Insights_CI `generate` verb, so the two paths can't drift: the
 * top-N items by score go through the LLM, with a ranked-list fallback when
 * there's no client or the call fails / returns empty.
 *
 * @package Newspack_AI_Newsletter
 */

namespace Newspack_AI_Newsletter;

use Newspack_Nodes\Core;

\defined( 'ABSPATH' ) || exit;

class Digest_Composer {

	private const TOP_N      = 10;
	private const MAX_TOKENS = 1500;

	/**
	 * Compose a markdown digest from accumulated items.
	 *
	 * @param array<int,array<array-key,mixed>> $items   Accumulated summarized items.
	 * @param LLM_Client|null                   $client  LLM client, or null to skip straight to the fallback.
	 * @param string                            $profile The relevance profile for the briefing prompt.
	 */
	public static function compose( array $items, ?LLM_Client $client, string $profile ): string {
		$draft = null;
		if ( $client instanceof LLM_Client ) {
			try {
				$draft = $client->chat(
					Prompts::digest( self::top_items( $items, self::TOP_N ), $profile ),
					[ 'max_tokens' => self::MAX_TOKENS ]
				);
			} catch ( \RuntimeException $e ) {
				// Rate-limited; an LLM failure NEVER throws out of compose — fall back to the ranked list.
				Core::print_less_often( 'AI digest compose failed: ' . $e->getMessage() );
				$draft = null;
			}
		}
		if ( null === $draft || '' === \trim( $draft ) ) {
			return self::render_ranked_list( $items );
		}
		return $draft;
	}

	/**
	 * Render the accumulated summaries to a markdown bullet list — the no-AI fallback.
	 *
	 * @param array<int,array<array-key,mixed>> $items Accumulated summarized items.
	 */
	private static function render_ranked_list( array $items ): string {
		$lines = [ '# Newsletter draft', '' ];
		foreach ( $items as $item ) {
			$summary = $item['summary'] ?? '';
			$lines[] = '- ' . ( \is_string( $summary ) ? $summary : '' );
		}
		return \implode( "\n", $lines ) . "\n";
	}

	/**
	 * The top $n items, highest `score` first.
	 *
	 * @param array<int,array<array-key,mixed>> $items Accumulated items.
	 * @return array<int,array<array-key,mixed>>
	 */
	private static function top_items( array $items, int $n ): array {
		\usort(
			$items,
			static fn ( array $a, array $b ): int => self::score_of( $b ) <=> self::score_of( $a )
		);
		return \array_slice( $items, 0, $n );
	}

	/**
	 * Read an item's `score` as a float; absent or non-numeric becomes 0.
	 *
	 * @param array<array-key,mixed> $item
	 */
	private static function score_of( array $item ): float {
		$score = $item['score'] ?? 0;
		return \is_numeric( $score ) ? (float) $score : 0.0;
	}
}
