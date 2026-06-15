<?php
/**
 * Newspack AI Newsletter (application) configuration.
 *
 * App-level overrides layered onto the newspack-nodes substrate defaults. The
 * `<config:logs_dir>` / `<config:offsets_dir>` topology tokens derive from
 * `base_directory`; the partition retention keys feed the `scored` partition.
 *
 * @package Newspack_AI_Newsletter
 */

\defined( 'ABSPATH' ) || exit;

return [
	// Per-application data root. The substrate derives logs/ and offsets/ under it.
	'base_directory' => '/tmp/newspack-ai-newsletter',

	// `scored` partition retention (mirrors the teaching example):
	//   num_segments: retained per partition (count cap).
	//   segment_size: max bytes before rotation.
	//   max_lifespan: minimum retention seconds; deletion requires BOTH over
	//                 num_segments AND older than max_lifespan.
	'num_partitions' => 1,
	'num_segments'   => 2,
	'segment_size'   => 64 * 1024 * 1024,
	'max_lifespan'   => 86400,
];
