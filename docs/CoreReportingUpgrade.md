# Core Reporting Upgrade

This upgrade provides a responsive, mobile-first and desktop-ready reporting flow without weakening moderation, Bangladesh map validation, evidence privacy, or duplicate-consolidation protections.

## What changes

- **Two-level taxonomy:** `categories.parent_id` and `categories.is_reportable` distinguish a main category from a citizen-selectable subcategory. Existing category IDs remain intact and are placed under the seeded **Roads and safe mobility** parent. Administrators manage the taxonomy at **Admin → Reporting categories**.
- **Familiar detailed reporting flow:** citizens use the established four steps: **location → main category/subcategory → same-category nearby reports → details and photos**. The familiar title, road/landmark context, description, area suggestion, and photo controls remain available. Photos are optional but strongly encouraged.
- **Category/subcategory controls:** the report form uses the same familiar select-control style for a main category followed by a reportable subcategory. Only the selected active leaf category is submitted, so nearby matching remains category-aware.
- **Location-name fallback:** a report can be `exact`, `approximate`, or `text_only`. Text-only reports require a place/landmark name and are stored with `location_review_status = needs_location_review`. They are excluded from public-map queries, duplicate merging, and automatic routing until a moderator explicitly adds a valid Bangladesh point in the issue editor.
- **Category-aware duplicates:** the nearby API accepts `category_id`; the report flow only compares active public issues in the selected reportable subcategory.
- **Ready-made AI service templates:** under **Admin → AI report assistance**, staff normally choose Google Gemini Flash, OpenAI Responses API, or Anthropic Claude Haiku and paste an API key. The known secure endpoint and model are set server-side. A custom OpenAI-compatible Chat Completions, OpenAI Responses, Gemini, or Anthropic provider remains available under an advanced disclosure. Providers use encrypted keys, priority/fallback order, feature enablement, and global/per-user/provider request caps.
- **AI connection test:** after saving a key, an administrator can use **Test saved connection**. It sends no citizen data or image and returns a safe success/failure message, helping distinguish a saved setting from a usable provider account/model/quota.
- **Optional photo draft:** after choosing a photo and explicit consent, a citizen can request an AI draft and explicitly apply its editable title, description, and proposed category. If the category changes, nearby matching is repeated. A citizen can always complete the report manually.
- **Windows-local photo reliability:** before writing evidence, the server creates the local `evidence/originals` and `evidence/pending` folders explicitly. Storage paths and provider errors are kept out of citizen-facing messages.

## Privacy and safety boundaries

- Evidence handling and moderation remain unchanged: published photos are still processed server-side and only become public after moderation.
- AI receives one temporary, resized JPEG re-encode with EXIF stripped. It receives no account data, coordinates, area-routing data, or citizen free-text description.
- The administrator connection test sends neither a citizen image nor citizen data.
- The server validates the provider response against active reportable category IDs and bounded title, description, confidence, and uncertainty fields.
- AI output never publishes a report, moderates evidence, identifies people, chooses an authority, assigns emergency status, or declares a repair complete.
- A place name is reviewer context, **not** a precise public address claim.
- A cloud provider's free/free-tier quota is account- and provider-dependent, never guaranteed or unlimited. Keep conservative caps and leave manual reporting available.

## Ward readiness

The data model continues to support the existing `admin_areas.level = ward` value. This upgrade deliberately does **not** claim automatic ward resolution: the current locator dataset does not contain authoritative ward polygons. A future ward-data patch should add maintained ward hierarchy/boundaries and point-in-polygon lookup, including city-corporation, pourashava, and union ward variants. Until then, routing continues to rely on a verified pin and the currently resolved administrative hierarchy.

## Deployment

1. Start with a database backup and a clean checkout of the expected baseline.
2. Apply the Core Reporting patch/hotfix and all required follow-up patches in order. Run `php artisan migrate --force` only if the Core Reporting migration is not already **Ran**.
3. Run `php artisan optimize:clear`.
4. Run `php artisan test` in an environment with PHP and project dependencies installed.
5. Visit **Admin → Reporting categories** to review the seeded hierarchy. Do not deactivate a subcategory that is still needed for new reports.
6. AI is off by default. At **Admin → AI report assistance**, paste a key for a ready-made service, save, use **Test saved connection**, then enable both that provider and the feature. Test with a non-sensitive road image and verify that manual reporting remains available.

No Vite source asset changes are required by this upgrade. On hosting that cannot run Node, the normal existing build/upload procedure still applies only if unrelated frontend assets are changed.
