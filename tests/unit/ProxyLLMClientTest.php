<?php
namespace Newspack_AI_Newsletter\Tests;

use Newspack_AI_Newsletter\Proxy_LLM_Client;
use PHPUnit\Framework\TestCase;

final class ProxyLLMClientTest extends TestCase {
	protected function tearDown(): void {
		Proxy_LLM_Client::$http_post = null;
	}

	public function test_sends_feature_header_bearer_and_model_and_parses_content(): void {
		$seen = null;
		Proxy_LLM_Client::$http_post = static function ( string $url, array $args ) use ( &$seen ) {
			$seen = [ 'url' => $url, 'args' => $args ];
			return [ 'response' => [ 'code' => 200 ], 'body' => \json_encode(
				[ 'choices' => [ [ 'message' => [ 'content' => 'A one-line summary.' ] ] ] ]
			) ];
		};
		$client = new Proxy_LLM_Client( 'https://proxy.test/v1', 'SEKRET', 'gpt-oss-120b', 'newspack-ai-newsletter' );
		$out    = $client->chat( [ [ 'role' => 'user', 'content' => 'summarize this' ] ], [ 'max_tokens' => 120 ] );

		$this->assertSame( 'A one-line summary.', $out );
		$this->assertSame( 'https://proxy.test/v1/chat/completions', $seen['url'] );
		$this->assertSame( 'Bearer SEKRET', $seen['args']['headers']['Authorization'] );
		$this->assertSame( 'newspack-ai-newsletter', $seen['args']['headers']['X-WPCOM-AI-Feature'] );
		$body = \json_decode( $seen['args']['body'], true );
		$this->assertSame( 'gpt-oss-120b', $body['model'] );
		$this->assertSame( 120, $body['max_tokens'] );
		$this->assertSame( 'summarize this', $body['messages'][0]['content'] );
	}

	public function test_throws_on_non_200(): void {
		Proxy_LLM_Client::$http_post = static fn () => [ 'response' => [ 'code' => 503 ], 'body' => 'unavailable' ];
		$client = new Proxy_LLM_Client( 'https://proxy.test/v1', 'SEKRET', 'gpt-oss-120b', 'newspack-ai-newsletter' );
		$this->expectException( \RuntimeException::class );
		$client->chat( [ [ 'role' => 'user', 'content' => 'x' ] ] );
	}
}
