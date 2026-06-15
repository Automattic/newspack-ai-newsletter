<?php
/**
 * @package Newspack_AI_Newsletter
 */

namespace Newspack_AI_Newsletter\Tests;

use Newspack_AI_Newsletter\Settings;
use Newspack_Nodes\Config_System\Field;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {
	public function test_declares_ai_and_secret_fields(): void {
		$keys = \array_map( static fn ( Field $f ): string => $f->key, Settings::fields() );
		foreach (
			[
				'ai_proxy_base_url',
				'ai_proxy_token',
				'ai_model',
				'ai_feature',
				'github_token',
				'linear_token',
				'feeds',
				'digest_schedule',
				'relevance_profile',
			] as $k
		) {
			$this->assertContains( $k, $keys, "missing field $k" );
		}
	}

	public function test_secret_fields_are_marked_secret(): void {
		$by_key = [];
		foreach ( Settings::fields() as $f ) {
			$by_key[ $f->key ] = $f;
		}
		foreach ( [ 'ai_proxy_token', 'github_token', 'linear_token' ] as $k ) {
			$this->assertTrue( ! empty( $by_key[ $k ]->register_args['secret'] ), "$k should be secret" );
		}
	}

	public function test_non_secret_fields_are_not_marked_secret(): void {
		$by_key = [];
		foreach ( Settings::fields() as $f ) {
			$by_key[ $f->key ] = $f;
		}
		foreach ( [ 'ai_proxy_base_url', 'ai_model', 'ai_feature', 'feeds' ] as $k ) {
			$this->assertTrue( empty( $by_key[ $k ]->register_args['secret'] ), "$k should not be secret" );
		}
	}

	public function test_defaults_for_ai_proxy_fields(): void {
		$by_key = [];
		foreach ( Settings::fields() as $f ) {
			$by_key[ $f->key ] = $f;
		}
		$this->assertSame(
			'https://public-api.wordpress.com/wpcom/v2/ai-api-proxy/v1',
			$by_key['ai_proxy_base_url']->register_args['default'] ?? null
		);
		$this->assertSame( 'gpt-oss-120b', $by_key['ai_model']->register_args['default'] ?? null );
		$this->assertSame( 'newspack-ai-newsletter', $by_key['ai_feature']->register_args['default'] ?? null );
	}
}
