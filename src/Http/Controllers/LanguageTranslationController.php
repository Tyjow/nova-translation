<?php

namespace Joedixon\NovaTranslation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controller;
use JoeDixon\Translation\Drivers\Translation;
use JoeDixon\Translation\Http\Requests\TranslationRequest;

class LanguageTranslationController extends Controller
{
    private $translation;

    public function __construct(Translation $translation)
    {
        $this->translation = $translation;
    }

    public function index(Request $request, $language)
    {
        $languages = $this->translation->allLanguages();

        // set collection to array
        $groups = $this->translation->getGroupsFor(config('app.locale'))->prepend('single')->toArray();

        // Looking for choose only specific file into the group
        $groupUsed = ['trad', 'meta'];
        $groups = new Collection(array_intersect($groupUsed, $groups));

        $translations = $this->translation->filterTranslationsFor($language, $request->get('search'));

        // get translations only with specific group
        $translations = $translations->get('group')->filter(function ($values, $group) use ($groupUsed) {
            $sortedGroups = [];
            foreach($groupUsed as $used)
            {
                if($group === $used)
                {
                    $sortedGroups[] = $group;
                }
            }
            return $sortedGroups;
        });

        $translations = new Collection(['group' => $translations]);

        if ($request->has('group') && $request->get('group')) {
            if ($request->get('group') === 'single') {
                $translations = $translations->get('single');
                $translations = new Collection(['single' => $translations]);
            } else {
                $translations = $translations->get('group')->filter(function ($values, $group) use ($request) {
                    return $group === $request->get('group');
                });

                $translations = new Collection(['group' => $translations]);
            }
        }

        $currentPage = $request->get('page');
        $perPage = $request->get('per_page');
        $offset = $currentPage - 1;

        return response()->json([
            'source_language' => config('app.locale'),
            'groups' => $groups,
            'languages' => $languages,
            'translations' => new Paginator(array_slice($this->formatTranslations($translations, $language), $offset * $perPage), $perPage, $currentPage)
        ]);
    }

    public function update(Request $request, $language)
    {
        if (! str_contains($request->get('group'), 'single')) {
            $this->translation->addGroupTranslation($language, $request->get('group'), $request->get('key'), $request->get('value') ?: '');
        } else {
            $this->translation->addSingleTranslation($language, $request->get('group'), $request->get('key'), $request->get('value') ?: '');
        }

        return response()->json(['success' => true]);
    }

    public function store(TranslationRequest $request, $language)
    {
        if ($request->has('group') && $request->get('group')) {
            $namespace = $request->has('namespace') && $request->get('namespace') ? "{$request->get('namespace')}::" : '';
            $this->translation->addGroupTranslation($language, "{$namespace}{$request->get('group')}", $request->get('key'), $request->get('value') ?: '');
        } else {
            $this->translation->addSingleTranslation($language, $request->get('key'), $request->get('value') ?: '');
        }

        return response()->json(['success' => true]);
    }

    private function formatTranslations($translations, $language)
    {
        $formattedTranslations = [];
        foreach ($translations as $type => $values) {
            foreach ($values as $group => $translations) {
                foreach ($translations as $key => $translation) {
                    // if the translation is an array, ignore it. This isn't
                    // supported by the UI at present
                    if (is_array($translation[config('app.locale')])) {
                        continue;
                    }
                    $formattedTranslations[] = [
                            'id' => str_random(20),
                            'type' => $type,
                            'group' => $group,
                            'key' => $key,
                            'translations' => $translation
                        ];
                }
            }
        }
        return $formattedTranslations;
    }
}
