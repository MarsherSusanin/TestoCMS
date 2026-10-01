<?php

namespace App\Modules\Content\Services;

use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Modules\Content\Support\LocalizedContentHelpers;
use App\Modules\Content\Support\TranslationInputMappingHelpers;
use Illuminate\Validation\ValidationException;

/** PATCH merges locale fields; PUT and web forms replace the translation set. */
class TranslationUpdateService
{
    use LocalizedContentHelpers;
    use TranslationInputMappingHelpers;

    public function prepare(Page|Post $entity, array $validated, User $actor, bool $merge): array
    {
        $submitted = $this->translationsInputByLocale($validated['translations'] ?? []);
        $removed = array_map(static fn (mixed $locale): string => strtolower((string) $locale), $validated['remove_translations'] ?? []);
        if (array_intersect(array_keys($submitted), $removed) !== []) {
            throw ValidationException::withMessages(['remove_translations' => ['A locale cannot be updated and removed in the same request.']]);
        }

        if (! $merge) {
            return $validated['translations'] ?? [];
        }

        $existing = $entity->translations()->get()->keyBy('locale');
        $translations = [];
        foreach ($existing as $locale => $translation) {
            if (! in_array($locale, $removed, true)) {
                $translations[$locale] = $translation->toArray();
            }
        }

        foreach ($submitted as $locale => $input) {
            $previous = $translations[$locale] ?? [];
            if (! $existing->has($locale) && (empty($input['title']) || empty($input['slug']))) {
                throw ValidationException::withMessages(["translations.$locale" => ['A new translation requires title and slug.']]);
            }
            if (($input['content_format'] ?? null) === 'markdown'
                && ($previous['content_format'] ?? 'html') !== 'markdown'
                && ! array_key_exists('content_markdown', $input)) {
                throw ValidationException::withMessages(["translations.$locale.content_markdown" => ['Markdown content is required when switching to Markdown.']]);
            }
            if (! $this->actorMayUseCustomCode($actor)) {
                if (array_key_exists('custom_head_html', $input) && ($input['custom_head_html'] ?? null) !== ($previous['custom_head_html'] ?? null)) {
                    abort(403, 'Custom head HTML is limited to advanced roles.');
                }
                if (isset($input['content_blocks']) && $this->containsRestrictedCodeBlocks($input['content_blocks']) && $input['content_blocks'] !== ($previous['content_blocks'] ?? null)) {
                    abort(403, 'Custom code blocks are limited to advanced roles.');
                }
            }
            $translations[$locale] = array_replace($translations[$locale] ?? [], $input);
        }

        if ($translations === []) {
            throw ValidationException::withMessages(['remove_translations' => ['At least one translation must remain.']]);
        }

        return array_map(static function (array $translation, string $locale): array {
            $translation['locale'] = $locale;

            return $translation;
        }, $translations, array_keys($translations));
    }
}
