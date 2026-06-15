# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Foundation of the `newspack-ai-newsletter` sibling plugin** — a team-intelligence
  digest built on the newspack-nodes substrate. This initial drop is the runnable
  pipeline skeleton + the shared seams; live sources, real LLM wiring, the dashboard,
  and publishing land in follow-on sub-projects.
  - Scaffold: build/lint/test tooling (composer + npm + jest + phpunit + phpcs +
    phpstan), the `@newspack-nodes/*` build aliases, release + pre-push wiring —
    mirroring `newspack-event-logger-nodes`.
  - Pipeline spine (ported from the `example-ai-newsletter` walkthrough, contracts
    preserved byte-for-byte): `Summarizer_Node`, `Scorer_Node`, `Digest_Builder_Node`,
    `Insights_CI_Node` (its `{sources,top,accumulated}` JSON model unchanged), plus a
    canned `Stub_Source_Node` so the graph runs end-to-end before real connectors exist.
  - Bootstrap + `topologies/newspack-ai-newsletter.tsl` (the real ingest→summarize→
    score→scored-partition→digest→log graph, stub source wired) + an Insights admin page
    (dashboard enqueue guarded until the React build ships).
  - `LLM_Client` interface + `Proxy_LLM_Client` targeting the Automattic AI API Proxy
    (OpenAI `chat/completions`; default model `gpt-oss-120b`), with a closure-HTTP test seam.
  - `Source` connector interface + `Settings` schema declaring the AI + connector config,
    flagging the credential fields (`ai_proxy_token`, `github_token`, `linear_token`) secret
    via the substrate's `register_args` extension seam.
  - See `dndocker/docs/superpowers/specs/2026-06-15-newspack-ai-newsletter-floorplan-design.md`.
